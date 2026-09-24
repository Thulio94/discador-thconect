function browserDependencies() {
    return {
        fetch: (...args) => window.fetch(...args),
        setTimeout: (...args) => window.setTimeout(...args),
        clearTimeout: (timerId) => window.clearTimeout(timerId),
        setInterval: (...args) => window.setInterval(...args),
        clearInterval: (timerId) => window.clearInterval(timerId),
        now: () => Date.now(),
        visibility: () => document.visibilityState,
        AbortController: window.AbortController,
        parse(html, selectors) {
            const documentFragment = new DOMParser().parseFromString(
                html,
                'text/html',
            );
            const regions = new Map();

            for (const selector of selectors) {
                const source = documentFragment.querySelector(selector);

                if (!source) {
                    throw new Error(`Polling response missing ${selector}`);
                }

                regions.set(selector, source.innerHTML);
            }

            return regions;
        },
        replace(regions) {
            const targets = new Map();

            for (const selector of regions.keys()) {
                const target = document.querySelector(selector);

                if (!target) {
                    throw new Error(`Polling target missing ${selector}`);
                }

                targets.set(selector, target);
            }

            for (const [selector, html] of regions) {
                const target = targets.get(selector);

                for (const child of target.children) {
                    window.Alpine.destroyTree(child);
                }

                target.innerHTML = html;

                for (const child of target.children) {
                    window.Alpine.initTree(child);
                }
            }
        },
        warn(error) {
            if (import.meta.env?.DEV) {
                console.warn(error);
            }
        },
    };
}

export function createPollingRegion(
    config,
    dependencies = browserDependencies(),
) {
    const intervalMs = (config.intervalSeconds ?? 10) * 1000;

    return {
        url: config.url,
        selectors: config.selectors,
        intervalMs,
        currentDelayMs: intervalMs,
        maxDelayMs: 60000,
        timerId: null,
        heartbeatTimerId: null,
        clockTimerId: null,
        controller: null,
        inFlight: false,
        refreshPending: false,
        stopped: false,
        heartbeatEnabled: config.heartbeat ?? false,
        nextPollAt: null,
        clockEnabled: config.clock ?? false,
        nowSeconds: Math.floor(dependencies.now() / 1000),

        start() {
            if (this.clockEnabled && this.clockTimerId === null) {
                this.clockTimerId = dependencies.setInterval(() => {
                    this.nowSeconds = Math.floor(dependencies.now() / 1000);
                }, 1000);
            }

            if (this.heartbeatEnabled && this.heartbeatTimerId === null) {
                this.nextPollAt = dependencies.now() + this.intervalMs;
                this.heartbeatTimerId = dependencies.setInterval(() => {
                    if (
                        !this.stopped
                        && dependencies.visibility() === 'visible'
                        && dependencies.now() >= this.nextPollAt
                    ) {
                        void this.poll();
                    }
                }, this.intervalMs);
            }

            this.schedule(this.intervalMs);
        },

        formatElapsed(startedAtSeconds, endedAtSeconds = null) {
            if (startedAtSeconds === null || startedAtSeconds === undefined || startedAtSeconds === '') {
                return '00:00:00';
            }

            const startedAt = Number(startedAtSeconds);
            const endedAt = endedAtSeconds === null
                ? this.nowSeconds
                : Number(endedAtSeconds);
            const seconds = Number.isFinite(startedAt) && Number.isFinite(endedAt)
                ? Math.max(0, Math.floor(endedAt - startedAt))
                : 0;
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = seconds % 60;

            return [hours, minutes, remainingSeconds]
                .map((value) => String(value).padStart(2, '0'))
                .join(':');
        },

        schedule(delay) {
            if (this.stopped || dependencies.visibility() !== 'visible') {
                return;
            }

            if (this.heartbeatEnabled) {
                this.nextPollAt = dependencies.now() + delay;

                return;
            }

            this.clearTimer();
            this.timerId = dependencies.setTimeout(() => this.poll(), delay);
        },

        async poll(force = false) {
            if (this.stopped) {
                return;
            }

            if (this.inFlight) {
                this.refreshPending ||= force;

                return;
            }

            if (!force && dependencies.visibility() !== 'visible') {
                return;
            }

            this.inFlight = true;
            this.controller = new dependencies.AbortController();

            try {
                const response = await dependencies.fetch(this.url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: this.controller.signal,
                });

                if ([401, 403, 404, 419].includes(response.status)) {
                    this.stopped = true;

                    return;
                }

                if (!response.ok) {
                    throw new Error(`Polling failed with ${response.status}`);
                }

                const regions = dependencies.parse(
                    await response.text(),
                    this.selectors,
                );
                dependencies.replace(regions);

                const headerSeconds = Number(
                    response.headers.get('X-Poll-Interval'),
                );
                this.intervalMs = Number.isFinite(headerSeconds)
                    && headerSeconds > 0
                    ? headerSeconds * 1000
                    : this.intervalMs;
                this.currentDelayMs = this.intervalMs;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    dependencies.warn(error);
                    this.currentDelayMs = Math.min(
                        Math.max(this.currentDelayMs * 2, 20000),
                        this.maxDelayMs,
                    );
                }
            } finally {
                this.inFlight = false;
                this.controller = null;

                if (this.refreshPending) {
                    this.refreshPending = false;
                    void this.poll(true);

                    return;
                }

                this.schedule(this.currentDelayMs);
            }
        },

        handleVisibilityChange() {
            if (dependencies.visibility() !== 'visible') {
                this.clearTimer();

                return;
            }

            void this.poll();
        },

        clearTimer() {
            if (this.timerId !== null) {
                dependencies.clearTimeout(this.timerId);
                this.timerId = null;
            }
        },

        destroy() {
            this.stopped = true;
            this.clearTimer();
            if (this.heartbeatTimerId !== null) {
                dependencies.clearInterval(this.heartbeatTimerId);
                this.heartbeatTimerId = null;
            }
            if (this.clockTimerId !== null) {
                dependencies.clearInterval(this.clockTimerId);
                this.clockTimerId = null;
            }
            this.controller?.abort();
        },
    };
}

export function pollingRegion(config) {
    return createPollingRegion(config);
}
