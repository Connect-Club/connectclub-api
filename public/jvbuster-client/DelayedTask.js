export class DelayedTask {
    constructor(fn, fnScope, fnArgs) {
        this.fn = fn;
        this.fnScope = fnScope;
        this.fnArgs = fnArgs;
        this.running = false;
        this.delayed = false;
        this.maxDelay = 0;
        this.intervalHandle = null;
    }
    async call() {
        this.running = true;
        this.delayed = false;
        this.cancel();
        await this.fn.apply(this.fnScope, this.fnArgs || []);
        this.running = false;
        if (this.delayed) {
            this.delay(this.maxDelay);
            this.maxDelay = 0;
        }
    }
    delay(delay) {
        if (this.running) {
            this.delayed = true;
            this.maxDelay = Math.max(this.maxDelay, delay);
        }
        else {
            this.cancel();
            this.intervalHandle = setInterval(this.call.bind(this), delay);
        }
    }
    cancel() {
        if (this.intervalHandle) {
            clearInterval(this.intervalHandle);
            this.intervalHandle = null;
        }
    }
}
