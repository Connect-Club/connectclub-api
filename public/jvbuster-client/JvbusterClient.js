import { DelayedTask } from './DelayedTask.js';
import { JvbusterClientBuilder } from "./JvbusterClientBuilder.js";

// noinspection JSUnusedGlobalSymbols
export class JvbusterClient {
    constructor(address, token, logger, onNewEndpointMessage, onNewMessageForDataChannel, videoBandwidth, audioBandwidth, onEndpoints) {
        this.address = address;
        this.token = token;
        this.logger = logger;
        this.onNewEndpointMessage = onNewEndpointMessage;
        this.onNewMessageForDataChannel = onNewMessageForDataChannel;
        this.videoBandwidth = videoBandwidth;
        this.audioBandwidth = audioBandwidth;
        this.onEndpoints = onEndpoints;
        this.lastMedias = new Map();
        this.lastAudioChannelIds = new Map();
        this.lastVideoChannelIds = new Map();
        this.lastStreams = new Map();
        this.lastSessionVersions = new Map();
        this.pinnedEndpoints = new Set();
        this.offers = [];
        this.firstAnswerProcessed = false;
        const tokenParts = token.split('.');
        if (tokenParts.length !== 3) {
            throw `Invalid token. Token contains ${tokenParts.length} parts, instead of 3.`;
        }
        const payload = atob(tokenParts[1]);
        logger.info('JvbusterClient', `tokenPayload=${payload}`);
        this.endpoint = JSON.parse(payload).endpoint;
        this.updateOffersDelayed = new DelayedTask(this.updateOffers, this);
        this.waitingSdpOffersAccept = false;
    }
    static newBuilder() {
        return new JvbusterClientBuilder();
    }
    static eqSet(a, b) {
        return a.size === b.size && Array.from(a).every(b.has.bind(b));
    }
    static getSdpOfferMeta(oldStreams, newStreams) {
        const result = {};
        const newStreamIds = new Set();
        for (const [streamId, { audioTracks, videoTracks }] of Object.entries(newStreams)) {
            newStreamIds.add(streamId);
            const oldStream = oldStreams[streamId];
            result[streamId] = {
                video: {
                    tracks: Array.from(videoTracks),
                    updated: !oldStream || !this.eqSet(oldStream.videoTracks, videoTracks)
                },
                audio: {
                    tracks: Array.from(audioTracks),
                    updated: !oldStream || !this.eqSet(oldStream.audioTracks, audioTracks)
                }
            };
        }
        for (const streamId of Object.keys(oldStreams).filter(s => !newStreamIds.has(s))) {
            result[streamId] = {
                video: {
                    tracks: [],
                    updated: true
                },
                audio: {
                    tracks: [],
                    updated: true
                }
            };
        }
        return result;
    }
    static getPrimarySsrcAttributes(channel) {
        const lines = [];
        for (const ssrc of channel.sources) {
            lines.push(`a=ssrc:${ssrc} cname:mixed`);
        }
        return lines.join('\r\n');
    }
    static getSsrcAttributes(channel) {
        const lines = [];
        for (const ssrcGroup of channel.ssrcGroups) {
            lines.push(`a=ssrc-group:${ssrcGroup.semantics} ${ssrcGroup.ssrcs.join(' ')}`);
        }
        for (const ssrc of channel.ssrcs) {
            lines.push(`a=ssrc:${ssrc} cname:${channel.endpoint}`);
        }
        return lines.join('\r\n');
    }
    async getOffers(offersResponse) {
        if (offersResponse.status !== 200) {
            throw `Bad response. Response(status=${offersResponse.status}, statusText=${offersResponse.statusText})`;
        }
        const offersText = await offersResponse.text();
        const offers = JSON.parse(offersText);
        if (!Array.isArray(offers)) {
            throw `Bad response. This is the wrong answer:\n${offersText}}`;
        }
        this.logger.debug('JvbusterClient', `offers=${offersText}`);
        return offers;
    }
    needNewOfferSdp(offer) {
        const lastAudioChannelIds = this.lastAudioChannelIds.get(offer.videobridgeId);
        if (!lastAudioChannelIds)
            return true;
        let checkedAudioChannels = 0;
        for (const audioChannel of offer.audioChannels) {
            if (this.pinnedEndpoints.has(audioChannel.endpoint)) {
                if (!lastAudioChannelIds.has(audioChannel.id))
                    return true;
                checkedAudioChannels++;
            }
        }
        if (checkedAudioChannels !== lastAudioChannelIds.size)
            return true;
        const lastVideoChannelIds = this.lastVideoChannelIds.get(offer.videobridgeId);
        if (!lastVideoChannelIds)
            return true;
        let checkedVideoChannels = 0;
        for (const videoChannel of offer.videoChannels) {
            if (this.pinnedEndpoints.has(videoChannel.endpoint)) {
                if (!lastVideoChannelIds.has(videoChannel.id))
                    return true;
                checkedVideoChannels++;
            }
        }
        if (checkedVideoChannels !== lastVideoChannelIds.size)
            return true;
        return false;
    }
    toOfferSdp(offer) {
        if (!this.needNewOfferSdp(offer))
            return null;
        const mediaIds = [`${offer.sctpConnectionId}`, `${offer.primaryAudioChannel.id}`, `${offer.primaryVideoChannel.id}`];
        const candidateAttributes = JvbusterClient.getCandidateAttributes(offer);
        const originalMedias = new Map();
        const newAudioChannelIds = new Set();
        const newStreams = {};
        for (const audioChannel of offer.audioChannels.filter(x => this.pinnedEndpoints.has(x.endpoint))) {
            let stream = newStreams[audioChannel.endpoint];
            if (!stream) {
                stream = newStreams[audioChannel.endpoint] = { videoTracks: new Set(), audioTracks: new Set() };
            }
            stream.audioTracks.add(audioChannel.id);
            newAudioChannelIds.add(audioChannel.id);
            mediaIds.push(`audio-${audioChannel.id}`);
            originalMedias.set(audioChannel.id, {
                id: audioChannel.id,
                type: 'audio',
                text: JvbusterClient.getAudioMedia(audioChannel, candidateAttributes)
            });
        }
        const newVideoChannelIds = new Set();
        for (const videoChannel of offer.videoChannels.filter(x => this.pinnedEndpoints.has(x.endpoint))) {
            let stream = newStreams[videoChannel.endpoint];
            if (!stream) {
                stream = newStreams[videoChannel.endpoint] = { videoTracks: new Set(), audioTracks: new Set() };
            }
            stream.videoTracks.add(videoChannel.id);
            newVideoChannelIds.add(videoChannel.id);
            mediaIds.push(`video-${videoChannel.id}`);
            originalMedias.set(videoChannel.id, {
                id: videoChannel.id,
                type: 'video',
                text: JvbusterClient.getVideoMedia(videoChannel, candidateAttributes)
            });
        }
        const medias = [];
        const lastMediaChannels = this.lastMedias.get(offer.videobridgeId) || [];
        for (const mediaChannel of lastMediaChannels) {
            if (mediaChannel.id) {
                let media = originalMedias.get(mediaChannel.id);
                if (media) {
                    originalMedias.delete(mediaChannel.id);
                }
                else {
                    let channelExists;
                    if (mediaChannel.type === 'video') {
                        channelExists = offer.videoChannels.some(x => x.id === mediaChannel.id);
                    }
                    else if (mediaChannel.type === 'audio') {
                        channelExists = offer.audioChannels.some(x => x.id === mediaChannel.id);
                    }
                    else {
                        throw 'not implemented';
                    }
                    media = {
                        id: channelExists ? mediaChannel.id : null,
                        type: mediaChannel.type,
                        text: `m=${mediaChannel.type} 0 RTP/SAVPF 0\r\n`
                    };
                }
                medias.push(media);
            }
            else {
                medias.push({
                    id: null,
                    type: mediaChannel.type
                });
            }
        }
        for (let i = 0; i < medias.length; i++) {
            if (medias[i].text)
                continue;
            const entry = originalMedias.entries().next();
            if (entry.done) {
                medias[i].text = JvbusterClient.getEmptyMedia(medias[i].type);
            }
            else {
                const { value: [key, value] } = entry;
                medias[i] = value;
                originalMedias.delete(key);
            }
        }
        medias.push(...originalMedias.values());
        //ignore text, cause lastMedias used only in ordering media sections
        this.lastMedias.set(offer.videobridgeId, medias.map(x => ({ id: x.id, type: x.type })));
        this.lastAudioChannelIds.set(offer.videobridgeId, newAudioChannelIds);
        this.lastVideoChannelIds.set(offer.videobridgeId, newVideoChannelIds);
        const oldStreams = this.lastStreams.get(offer.videobridgeId) || {};
        this.lastStreams.set(offer.videobridgeId, newStreams);
        let sessionVersion = (this.lastSessionVersions.get(offer.videobridgeId) || 0) + 1;
        this.lastSessionVersions.set(offer.videobridgeId, sessionVersion);
        return {
            text: [
                JvbusterClient.getHeader(offer, '', sessionVersion, mediaIds),
                JvbusterClient.getApplicationMedia(offer, candidateAttributes),
                JvbusterClient.getPrimaryAudioMedia(offer.primaryAudioChannel, candidateAttributes, this.audioBandwidth),
                JvbusterClient.getPrimaryVideoMedia(offer.primaryVideoChannel, candidateAttributes, this.videoBandwidth),
                ...medias.map(x => x.text)
            ].join(''),
            meta: JvbusterClient.getSdpOfferMeta(oldStreams, newStreams)
        };
    }
    getEndpoints() {
        const endpoints = new Set();
        for (const offer of this.offers) {
            for (const audioChannel of offer.audioChannels) {
                endpoints.add(audioChannel.endpoint);
            }
            for (const videoChannel of offer.videoChannels) {
                endpoints.add(videoChannel.endpoint);
            }
        }
        return [...endpoints];
    }
    clearInternalStructures() {
        this.updateOffersDelayed.cancel();
        this.pinnedEndpoints.clear();
        this.lastMedias.clear();
        this.lastAudioChannelIds.clear();
        this.lastVideoChannelIds.clear();
        this.lastStreams.clear();
        this.lastSessionVersions.clear();
        this.offers = [];
        this.firstAnswerProcessed = false;
    }
    async start(speaker = true) {
        this.logger.info('JvbusterClient', `start(speaker=${speaker})`);
        this.clearInternalStructures();
        const offersResponse = await fetch(`${this.address}/signaling-new/new-offers?speaker=${speaker}`, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + this.token
            }
        });
        this.offers = await this.getOffers(offersResponse);
        if (this.onEndpoints) {
            this.onEndpoints(this.getEndpoints());
        }
        return this.offers.map(x => this.toOfferSdp(x));
    }
    async stop() {
        this.logger.info('JvbusterClient', 'stop');
        this.clearInternalStructures();
        await fetch(`${this.address}/signaling`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + this.token
            }
        });
    }
    async processAnswers(sdps) {
        this.logger.info('JvbusterClient', 'processAnswers');
        if (this.offers.length === 0) {
            throw 'Offers have not yet been received from the server. Did you forget to call `start` method?';
        }
        const answerSdp = sdps.join('\r\n');
        const response = await fetch(`${this.address}/signaling/answers`, {
            method: 'POST',
            body: answerSdp,
            headers: {
                'Authorization': 'Bearer ' + this.token,
                'Content-Type': 'text/plain;charset=utf-8'
            }
        });
        if (response.status !== 200) {
            throw `Answer processing error. Response(status=${response.status}, statusText=${response.statusText})`;
        }
        this.firstAnswerProcessed = true;
    }
    processDataChannelMessage(data) {
        const evt = JSON.parse(data);
        if (evt.colibriClass === 'EndpointMessage' && evt.from !== this.endpoint) {
            if (this.onNewEndpointMessage)
                this.onNewEndpointMessage(evt.from, evt.msgPayload);
        }
        else if (evt.colibriClass === 'EndpointConnectivityStatusChangeEvent' && evt.endpoint !== this.endpoint) {
            if (evt.active) {
                this.updateOffersDelayed.delay(100);
            }
        }
        else if (evt.colibriClass === 'EndpointExpiredEvent' && evt.endpoint !== this.endpoint) {
            this.updateOffersDelayed.delay(100);
        }
        else if (evt.colibriClass === 'NewVideobridgeAddedToConference') {
            throw 'Not implemented';
            // const sdpOfferResponse = await fetch(`${this._address}/signaling/offer/${evt.videobridgeId}?videoBandwidth=${this._videoBandwidth}`, {
            //     method: 'GET',
            //     headers: {
            //         'Authorization': 'Bearer ' + this._token
            //     }
            // });
            // this._sdps.push({type: 'offer', sdp: await sdpOfferResponse.text()});
            // this._onNewSdp(this._sdps);
        }
    }
    async updateOffers() {
        const offersResponse = await fetch(`${this.address}/signaling-new/current-offers`, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + this.token
            }
        });
        this.offers = await this.getOffers(offersResponse);
        if (this.onEndpoints) {
            this.onEndpoints(this.getEndpoints());
        }
    }
    subscribe(endpoints) {
        this.logger.info('JvbusterClient', `subscribe(endpoints=${endpoints})`);
        if (!this.firstAnswerProcessed || this.waitingSdpOffersAccept)
            return;
        this.waitingSdpOffersAccept = true;
        this.pinnedEndpoints.clear();
        endpoints.forEach(this.pinnedEndpoints.add, this.pinnedEndpoints);
        this.getEndpoints()
            .filter(x => x.startsWith('screen-'))
            .forEach(this.pinnedEndpoints.add, this.pinnedEndpoints);
        const sdpOffers = this.offers.map(x => this.toOfferSdp(x));
        const pinnedEndpointsFromSdp = new Set([...this.lastStreams.values()].flatMap(x => Object.keys(x)));
        const pinnedUUIDEndpoints = this.offers
            .flatMap(x => x.endpoints)
            .filter(x => pinnedEndpointsFromSdp.has(x.id))
            .map(x => x.uuid);
        this.logger.info('JvbusterClient', `pinnedEndpointsFromSdp=${[...pinnedEndpointsFromSdp]}, pinnedUUIDEndpoints=${pinnedUUIDEndpoints}`);
        if (!sdpOffers.some(x => x)) {
            this.sendPinnedUUIDEndpointsChangedEvent(pinnedUUIDEndpoints);
            this.waitingSdpOffersAccept = false;
            return;
        }
        return {
            sdpOffers: sdpOffers,
            accepted: () => {
                this.sendPinnedUUIDEndpointsChangedEvent(pinnedUUIDEndpoints);
                if (!this.waitingSdpOffersAccept) {
                    this.logger.warn('JvbusterClient', '`waitingSdpOffersAccept` should be `true` here');
                }
                this.waitingSdpOffersAccept = false;
            }
        };
    }
    sendPinnedUUIDEndpointsChangedEvent(endpoints) {
        this.logger.info('JvbusterClient', `sendPinnedUUIDEndpointsChangedEvent(endpoints=${endpoints})`);
        if (!this.onNewMessageForDataChannel) {
            this.logger.info('JvbusterClient', `onNewMessageForDataChannel is null, ignoring PinnedUUIDEndpointsChangedEvent`);
            return;
        }
        ;
        const msg = JSON.stringify({
            colibriClass: 'PinnedUUIDEndpointsChangedEvent',
            pinnedUUIDEndpoints: endpoints
        });
        this.onNewMessageForDataChannel(msg);
    }
    sendEndpointMessage(msgPayload, to = '') {
        if (!this.onNewMessageForDataChannel)
            return;
        const msg = `{
            "colibriClass": "EndpointMessage",
            "to": "${to}",
            "msgPayload": ${JSON.stringify(msgPayload)}
        }`;
        this.onNewMessageForDataChannel(msg);
    }
}
JvbusterClient.getCandidateAttributes = (offer) => offer.candidates
    .map(x => `a=candidate:${x.foundation} ${x.component} ${x.protocol.toLocaleLowerCase()} ${x.priority} ${x.ip} ${x.port} typ ${x.type.toLocaleLowerCase()}${x.relAddr ? ' raddr ' + x.relAddr : ''}${x.relPort ? ' rport ' + x.relPort : ''} generation ${x.generation}`)
    .concat('a=end-of-candidates')
    .join('\r\n');
JvbusterClient.getApplicationMedia = (offer, candidateAttributes) => `m=application 1 DTLS/SCTP 5000\r
c=IN IP4 0.0.0.0\r
a=sctpmap:5000 webrtc-datachannel 1024\r
a=sendrecv\r
a=mid:${offer.sctpConnectionId}\r
a=rtcp-mux\r
${candidateAttributes}\r
`;
JvbusterClient.getPrimaryAudioMedia = (primaryAudioChannel, candidateAttributes, bandwidth) => `m=audio 1 RTP/SAVPF 111\r
c=IN IP4 0.0.0.0\r
b=AS:${bandwidth || 16}\r
a=rtcp:1 IN IP4 0.0.0.0\r
a=${primaryAudioChannel.direction}\r
a=rtpmap:111 opus/48000/2\r
a=fmtp:111 minptime=10; stereo=0; useinbandfec=1\r
a=rtcp-fb:111 transport-cc\r
a=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\r
a=extmap:5 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\r
a=mid:${primaryAudioChannel.id}\r
a=msid:mixedmslabel audio\r
${JvbusterClient.getPrimarySsrcAttributes(primaryAudioChannel)}\r
a=rtcp-mux\r
${candidateAttributes}\r
`;
JvbusterClient.getPrimaryVideoMedia = (primaryVideoChannel, candidateAttributes, bandwidth) => `m=video 1 RTP/SAVPF 100 96\r
c=IN IP4 0.0.0.0\r
b=AS:${bandwidth || 200}\r
a=rtcp:1 IN IP4 0.0.0.0\r
a=${primaryVideoChannel.direction}\r
a=rtpmap:100 VP8/90000\r
a=fmtp:100 max-fr=30; max-recv-height=320; max-recv-width=480\r
a=rtcp-fb:100 ccm fir\r
a=rtcp-fb:100 nack\r
a=rtcp-fb:100 nack pli\r
a=rtcp-fb:100 transport-cc\r
a=rtpmap:96 rtx/90000\r
a=fmtp:96 apt=100\r
a=extmap:3 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\r
a=extmap:5 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\r
a=mid:${primaryVideoChannel.id}\r
a=msid:mixedmslabel video\r
${JvbusterClient.getPrimarySsrcAttributes(primaryVideoChannel)}\r
a=rtcp-mux\r
${candidateAttributes}\r
`;
JvbusterClient.getAudioMedia = (audioChannel, candidateAttributes) => `m=audio 1 RTP/SAVPF 111\r
c=IN IP4 0.0.0.0\r
a=rtcp:1 IN IP4 0.0.0.0\r
a=sendonly\r
a=rtpmap:111 opus/48000/2\r
a=fmtp:111 minptime=10; stereo=0; useinbandfec=1\r
a=rtcp-fb:111 transport-cc\r
a=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\r
a=extmap:5 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\r
a=mid:audio-${audioChannel.id}\r
a=rtcp-mux\r
a=msid:${audioChannel.endpoint} ${audioChannel.id}\r
${JvbusterClient.getSsrcAttributes(audioChannel)}\r
${candidateAttributes}\r
`;
JvbusterClient.getVideoMedia = (videoChannel, candidateAttributes) => `m=video 1 RTP/SAVPF 100\r
c=IN IP4 0.0.0.0\r
a=rtcp:1 IN IP4 0.0.0.0\r
a=sendonly\r
a=rtpmap:100 VP8/90000\r
a=fmtp:100 max-fr=30; max-recv-height=320; max-recv-width=480\r
a=rtcp-fb:100 ccm fir\r
a=rtcp-fb:100 nack\r
a=rtcp-fb:100 nack pli\r
a=rtcp-fb:100 transport-cc\r
a=extmap:3 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\r
a=extmap:5 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\r
a=mid:video-${videoChannel.id}\r
a=rtcp-mux\r
${candidateAttributes}\r
a=msid:${videoChannel.endpoint} ${videoChannel.id}\r
${JvbusterClient.getSsrcAttributes(videoChannel)}\r
`;
JvbusterClient.getEmptyMedia = (type) => `m=${type} 0 RTP/SAVPF 0\r\n`;
JvbusterClient.getHeader = (offer, sessionId, sessionVersion, medias) => `v=0\r
o=- ${sessionId} ${sessionVersion} IN IP4 0.0.0.0\r
s=-\r
t=0 0\r
a=ice-ufrag:${offer.ufrag}\r
a=ice-pwd:${offer.pwd}\r
a=group:BUNDLE ${medias.join(' ')}\r
a=msid-semantic: WMS *\r
${offer.fingerprints.map(f => `a=fingerprint:${f.hash} ${f.value}\r\na=setup:actpass`).join('\r\n')}\r
m=text 0 udp 0\r
a=mid:confId-${offer.conferenceId}\r
`;
export default JvbusterClient;
