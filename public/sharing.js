// noinspection ES6PreferShortImport
import { JvbusterClient } from './jvbuster-client/index.js';

const urlParams = new URLSearchParams(location.search);
const videoBandwidth = urlParams.get('videoBandwidth') || 400;
const audioBandwidth = urlParams.get('audioBandwidth') || 64;
const forcePassiveDtls = urlParams.get('forcePassiveDtls') || false;
const simulcast = urlParams.get('simulcast') || false;

let peerConnection = null;
let jvbusterClient = null;

// Helper method to munge an SDP to enable simulcasting (Chrome only)
function mungeSdpForSimulcasting(sdp) {
    // Let's munge the SDP to add the attributes for enabling simulcasting
    // (based on https://gist.github.com/ggarber/a19b4c33510028b9c657)
    var lines = sdp.split("\r\n");
    var video = false;
    var ssrc = [ -1 ], ssrc_fid = [ -1 ];
    var cname = null, msid = null, mslabel = null, label = null;
    var insertAt = -1;
    for(let i=0; i<lines.length; i++) {
        const mline = lines[i].match(/m=(\w+) */);
        if(mline) {
            const medium = mline[1];
            if(medium === "video") {
                // New video m-line: make sure it's the first one
                if(ssrc[0] < 0) {
                    video = true;
                } else {
                    // We're done, let's add the new attributes here
                    insertAt = i;
                    break;
                }
            } else {
                // New non-video m-line: do we have what we were looking for?
                if(ssrc[0] > -1) {
                    // We're done, let's add the new attributes here
                    insertAt = i;
                    break;
                }
            }
            continue;
        }
        if(!video)
            continue;
        var sim = lines[i].match(/a=ssrc-group:SIM (\d+) (\d+) (\d+)/);
        if(sim) {
            Janus.warn("The SDP already contains a SIM attribute, munging will be skipped");
            return sdp;
        }
        var fid = lines[i].match(/a=ssrc-group:FID (\d+) (\d+)/);
        if(fid) {
            ssrc[0] = fid[1];
            ssrc_fid[0] = fid[2];
            lines.splice(i, 1); i--;
            continue;
        }
        if(ssrc[0]) {
            var match = lines[i].match('a=ssrc:' + ssrc[0] + ' cname:(.+)')
            if(match) {
                cname = match[1];
            }
            match = lines[i].match('a=ssrc:' + ssrc[0] + ' msid:(.+)')
            if(match) {
                msid = match[1];
            }
            match = lines[i].match('a=ssrc:' + ssrc[0] + ' mslabel:(.+)')
            if(match) {
                mslabel = match[1];
            }
            match = lines[i].match('a=ssrc:' + ssrc[0] + ' label:(.+)')
            if(match) {
                label = match[1];
            }
            if(lines[i].indexOf('a=ssrc:' + ssrc_fid[0]) === 0) {
                lines.splice(i, 1); i--;
                continue;
            }
            if(lines[i].indexOf('a=ssrc:' + ssrc[0]) === 0) {
                lines.splice(i, 1); i--;
                continue;
            }
        }
        if(lines[i].length == 0) {
            lines.splice(i, 1); i--;
            continue;
        }
    }
    if(ssrc[0] < 0) {
        // Couldn't find a FID attribute, let's just take the first video SSRC we find
        insertAt = -1;
        video = false;
        for(let i=0; i<lines.length; i++) {
            const mline = lines[i].match(/m=(\w+) */);
            if(mline) {
                const medium = mline[1];
                if(medium === "video") {
                    // New video m-line: make sure it's the first one
                    if(ssrc[0] < 0) {
                        video = true;
                    } else {
                        // We're done, let's add the new attributes here
                        insertAt = i;
                        break;
                    }
                } else {
                    // New non-video m-line: do we have what we were looking for?
                    if(ssrc[0] > -1) {
                        // We're done, let's add the new attributes here
                        insertAt = i;
                        break;
                    }
                }
                continue;
            }
            if(!video)
                continue;
            if(ssrc[0] < 0) {
                var value = lines[i].match(/a=ssrc:(\d+)/);
                if(value) {
                    ssrc[0] = value[1];
                    lines.splice(i, 1); i--;
                    continue;
                }
            } else {
                let match = lines[i].match('a=ssrc:' + ssrc[0] + ' cname:(.+)')
                if(match) {
                    cname = match[1];
                }
                match = lines[i].match('a=ssrc:' + ssrc[0] + ' msid:(.+)')
                if(match) {
                    msid = match[1];
                }
                match = lines[i].match('a=ssrc:' + ssrc[0] + ' mslabel:(.+)')
                if(match) {
                    mslabel = match[1];
                }
                match = lines[i].match('a=ssrc:' + ssrc[0] + ' label:(.+)')
                if(match) {
                    label = match[1];
                }
                if(lines[i].indexOf('a=ssrc:' + ssrc_fid[0]) === 0) {
                    lines.splice(i, 1); i--;
                    continue;
                }
                if(lines[i].indexOf('a=ssrc:' + ssrc[0]) === 0) {
                    lines.splice(i, 1); i--;
                    continue;
                }
            }
            if(lines[i].length === 0) {
                lines.splice(i, 1); i--;
                continue;
            }
        }
    }
    if(ssrc[0] < 0) {
        // Still nothing, let's just return the SDP we were asked to munge
        Janus.warn("Couldn't find the video SSRC, simulcasting NOT enabled");
        return sdp;
    }
    if(insertAt < 0) {
        // Append at the end
        insertAt = lines.length;
    }
    // Generate a couple of SSRCs (for retransmissions too)
    // Note: should we check if there are conflicts, here?
    ssrc[1] = Math.floor(Math.random()*0xFFFFFFFF);
    ssrc[2] = Math.floor(Math.random()*0xFFFFFFFF);
    ssrc_fid[1] = Math.floor(Math.random()*0xFFFFFFFF);
    ssrc_fid[2] = Math.floor(Math.random()*0xFFFFFFFF);
    // Add attributes to the SDP
    for(var i=0; i<ssrc.length; i++) {
        if(cname) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' cname:' + cname);
            insertAt++;
        }
        if(msid) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' msid:' + msid);
            insertAt++;
        }
        if(mslabel) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' mslabel:' + mslabel);
            insertAt++;
        }
        if(label) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' label:' + label);
            insertAt++;
        }
        // Add the same info for the retransmission SSRC
        if(cname) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' cname:' + cname);
            insertAt++;
        }
        if(msid) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' msid:' + msid);
            insertAt++;
        }
        if(mslabel) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' mslabel:' + mslabel);
            insertAt++;
        }
        if(label) {
            lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' label:' + label);
            insertAt++;
        }
    }
    lines.splice(insertAt, 0, 'a=ssrc-group:FID ' + ssrc[2] + ' ' + ssrc_fid[2]);
    lines.splice(insertAt, 0, 'a=ssrc-group:FID ' + ssrc[1] + ' ' + ssrc_fid[1]);
    lines.splice(insertAt, 0, 'a=ssrc-group:FID ' + ssrc[0] + ' ' + ssrc_fid[0]);
    lines.splice(insertAt, 0, 'a=ssrc-group:SIM ' + ssrc[0] + ' ' + ssrc[1] + ' ' + ssrc[2]);
    sdp = lines.join("\r\n");
    if(!sdp.endsWith("\r\n"))
        sdp += "\r\n";
    return sdp;
}

async function start() {
    await stop();

    document.getElementById('start').disabled = true;
    document.getElementById('stop').disabled = true;

    try {
        const screenStream = await navigator.mediaDevices.getDisplayMedia({
            video: {height: 720, frameRate: 24},
            audio: {
                autoGainControl: false,
                channelCount: 2,
                echoCancellation: false,
                latency: 0,
                noiseSuppression: false,
                sampleRate: 48000,
                sampleSize: 16,
                volume: 1.0
              }
        });
        screenStream.oninactive = () => {
            stop();
        };

        peerConnection = new RTCPeerConnection({
            sdpSemantics: 'unified-plan'
        });

        // video tracks have to come first,
        // otherwise for some reason simulcast works bad
        screenStream.getVideoTracks()
            .forEach(track => peerConnection.addTrack(track, screenStream));
        screenStream.getAudioTracks()
            .forEach(track => peerConnection.addTrack(track, screenStream));

        peerConnection.addEventListener('datachannel', datachannelEvent => {
            datachannelEvent.channel.addEventListener(
                'message',
                messageEvent => jvbusterClient.processDataChannelMessage(messageEvent.data)
            );
        });

        peerConnection.addEventListener('connectionstatechange', event => {
            document.getElementById('connectionState').value = peerConnection.connectionState;
            if(peerConnection.connectionState === 'connected') {
                document.getElementById('start').disabled = true;
                document.getElementById('stop').disabled = false;
            } else if(peerConnection.connectionState === 'closed'
                || peerConnection.connectionState === 'disconnected'
                || peerConnection.connectionState === 'failed'
            ) {
                document.getElementById('start').disabled = false;
                document.getElementById('stop').disabled = true;
            }
        });

        jvbusterClient = JvbusterClient.newBuilder()
            .setToken(window.jitsiToken)
            .setAddress(window.jitsiAddress)
            .setLogger(console)
            .setVideoBandwidth(videoBandwidth)
            .setAudioBandwidth(audioBandwidth)
            .build()

        const sdpOffers = await jvbusterClient.start(true);
        await peerConnection.setRemoteDescription({
            type: 'offer',
            sdp: sdpOffers[0].text
        });
        const answer = await peerConnection.createAnswer();
        if (forcePassiveDtls) {
            answer.sdp = answer.sdp.replaceAll('a=setup:active', 'a=setup:passive')
        }
        if (simulcast) {
            answer.sdp = mungeSdpForSimulcasting(answer.sdp);
        }
        await peerConnection.setLocalDescription(answer);
        await jvbusterClient.processAnswers([answer.sdp]);
    } catch (e) {
        alert(e);
        await stop();
        document.getElementById('start').disabled = false;
        document.getElementById('stop').disabled = true;
    }
}

async function stop() {
    if(jvbusterClient) {
        await jvbusterClient.stop();
        jvbusterClient = null;
    }
    if(peerConnection) {
        await peerConnection.close();
        peerConnection = null;
    }
    document.getElementById('start').disabled = false;
    document.getElementById('stop').disabled = true;
    document.getElementById('connectionState').value = 'None';
}

window.start = start;
window.stop = window.onbeforeunload = stop;
