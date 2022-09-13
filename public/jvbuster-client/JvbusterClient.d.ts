export interface SdpOfferMeta {
    [streamId: string]: {
        readonly video: {
            readonly tracks: string[];
            readonly updated: boolean;
        };
        readonly audio: {
            readonly tracks: string[];
            readonly updated: boolean;
        };
    };
}
export interface SdpOffer {
    readonly text: string;
    readonly meta: SdpOfferMeta;
}
export interface Subscription {
    readonly sdpOffers: (SdpOffer | null)[];
    readonly accepted: () => void;
}
export interface Logger {
    debug(...data: any[]): void;
    info(...data: any[]): void;
    warn(...data: any[]): void;
    error(...data: any[]): void;
}
export declare class JvbusterClient {
    private readonly address;
    private readonly token;
    private readonly logger;
    private readonly onNewEndpointMessage?;
    private readonly onNewMessageForDataChannel?;
    private readonly videoBandwidth?;
    private readonly audioBandwidth?;
    private readonly onEndpoints?;
    private readonly endpoint;
    private readonly lastMedias;
    private readonly lastAudioChannelIds;
    private readonly lastVideoChannelIds;
    private readonly lastStreams;
    private readonly lastSessionVersions;
    private readonly pinnedEndpoints;
    private readonly updateOffersDelayed;
    private waitingSdpOffersAccept;
    private offers;
    private firstAnswerProcessed;
    constructor(address: string, token: string, logger: Logger, onNewEndpointMessage?: ((from: string, message: object) => void) | null | undefined, onNewMessageForDataChannel?: ((msg: string) => void) | null | undefined, videoBandwidth?: number | null | undefined, audioBandwidth?: number | null | undefined, onEndpoints?: ((endpoints: string[]) => void) | null | undefined);
    private static newBuilder;
    private static eqSet;
    private static getSdpOfferMeta;
    private static getPrimarySsrcAttributes;
    private static getSsrcAttributes;
    private getOffers;
    private needNewOfferSdp;
    private static readonly getCandidateAttributes;
    private static readonly getApplicationMedia;
    private static readonly getPrimaryAudioMedia;
    private static readonly getPrimaryVideoMedia;
    private static readonly getAudioMedia;
    private static readonly getVideoMedia;
    private static readonly getEmptyMedia;
    private static readonly getHeader;
    private toOfferSdp;
    private getEndpoints;
    private clearInternalStructures;
    start(speaker?: boolean): Promise<SdpOffer[]>;
    stop(): Promise<void>;
    processAnswers(sdps: string[]): Promise<void>;
    processDataChannelMessage(data: string): void;
    private updateOffers;
    subscribe(endpoints: string[]): Subscription | undefined;
    private sendPinnedUUIDEndpointsChangedEvent;
    sendEndpointMessage(msgPayload: object, to?: string): void;
}
export default JvbusterClient;
