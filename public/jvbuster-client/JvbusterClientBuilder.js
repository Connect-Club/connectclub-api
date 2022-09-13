import { JvbusterClient } from './JvbusterClient.js';
export class JvbusterClientBuilder {
    constructor() {
        this.address = '';
    }
    setAddress(address) {
        this.address = address;
        return this;
    }
    setToken(token) {
        this.token = token;
        return this;
    }
    setOnNewEndpointMessage(onNewEndpointMessage) {
        this.onNewEndpointMessage = onNewEndpointMessage;
        return this;
    }
    setOnNewMessageForDataChannel(onNewMessageForDataChannel) {
        this.onNewMessageForDataChannel = onNewMessageForDataChannel;
        return this;
    }
    setLogger(logger) {
        this.logger = logger;
        return this;
    }
    setVideoBandwidth(videoBandwidth) {
        this.videoBandwidth = videoBandwidth;
        return this;
    }
    setAudioBandwidth(audioBandwidth) {
        this.audioBandwidth = audioBandwidth;
        return this;
    }
    setOnEndpoints(onEndpoints) {
        this.onEndpoints = onEndpoints;
        return this;
    }
    build() {
        if (this.token == null) {
            throw 'token has no value';
        }
        if (this.logger == null) {
            throw 'logger has no value';
        }
        return new JvbusterClient(this.address, this.token, this.logger, this.onNewEndpointMessage, this.onNewMessageForDataChannel, this.videoBandwidth, this.audioBandwidth, this.onEndpoints);
    }
}
