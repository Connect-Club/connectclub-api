import { JvbusterClient, Logger } from './JvbusterClient.js';
export declare class JvbusterClientBuilder {
    private address;
    private token?;
    private logger?;
    private onNewEndpointMessage?;
    private onNewMessageForDataChannel?;
    private videoBandwidth?;
    private audioBandwidth?;
    private onEndpoints?;
    setAddress(address: string): JvbusterClientBuilder;
    setToken(token: string): JvbusterClientBuilder;
    setOnNewEndpointMessage(onNewEndpointMessage: (from: string, message: object) => void): JvbusterClientBuilder;
    setOnNewMessageForDataChannel(onNewMessageForDataChannel: (msg: string) => void): JvbusterClientBuilder;
    setLogger(logger: Logger): JvbusterClientBuilder;
    setVideoBandwidth(videoBandwidth: number): JvbusterClientBuilder;
    setAudioBandwidth(audioBandwidth: number): JvbusterClientBuilder;
    setOnEndpoints(onEndpoints: (endpoints: string[]) => void): JvbusterClientBuilder;
    build(): JvbusterClient;
}
