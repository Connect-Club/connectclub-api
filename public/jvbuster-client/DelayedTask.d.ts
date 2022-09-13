export declare class DelayedTask {
    private fn;
    private fnScope;
    private fnArgs?;
    private running;
    private delayed;
    private maxDelay;
    private intervalHandle;
    constructor(fn: Function, fnScope: any, fnArgs?: any[] | undefined);
    private call;
    delay(delay: number): void;
    cancel(): void;
}
