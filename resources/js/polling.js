function browserDependencies() {
    return {
        fetch: (...args) => window.fetch(...args),
        setTimeout: (...args) => window.setTimeout(...args),
        clearTimeout: (timerId) => window.clearTimeout(timerId),
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
        controller: null,
        inFlight: false,
        stopped: false,

        start() {
            this.schedule(this.intervalMs);
        },

        schedule(delay) {
            if (this.stopped || dependencies.visibility() !== 'visible') {
                return;
            }

            this.clearTimer();
            this.timerId = dependencies.setTimeout(() => this.poll(), delay);
        },

        async poll() {
            if (
                this.stopped
                || this.inFlight
                || dependencies.visibility() !== 'visible'
            ) {
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
            this.controller?.abort();
        },
    };
}

export function pollingRegion(config) {
    return createPollingRegion(config);
}
