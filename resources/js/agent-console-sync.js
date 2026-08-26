function browserDependencies() {
    return {
        fetch: (...args) => window.fetch(...args),
        setInterval: (...args) => window.setInterval(...args),
        clearInterval: (timerId) => window.clearInterval(timerId),
        visibility: () => document.visibilityState,
        hasDirtyForm: () => Boolean(document.querySelector('[data-agent-live-form][data-dirty="true"]')),
        reload: () => window.location.reload(),
    };
}

export function createAgentConsoleSync(config, dependencies = browserDependencies()) {
    return {
        url: config.url,
        signature: config.signature,
        intervalMs: (config.intervalSeconds ?? 5) * 1000,
        timerId: null,
        inFlight: false,
        stopped: false,
        pending: false,
        unsubscribe: null,

        start() {
            this.unsubscribe = config.subscribe?.(() => this.check(true)) ?? null;
            this.timerId = dependencies.setInterval(() => this.check(), this.intervalMs);
        },

        async check(force = false) {
            if (this.stopped || this.inFlight || (!force && dependencies.visibility() !== 'visible')) {
                return;
            }

            this.inFlight = true;

            try {
                const response = await dependencies.fetch(this.url, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if ([401, 403, 404, 419, 429].includes(response.status)) {
                    return;
                }

                if (!response.ok) {
                    throw new Error(`Agent state failed with ${response.status}`);
                }

                const state = await response.json();

                if (!state.signature || state.signature === this.signature) {
                    return;
                }

                if (dependencies.hasDirtyForm()) {
                    this.pending = true;
                    return;
                }

                dependencies.reload();
            } catch (error) {
                if (import.meta.env?.DEV) {
                    console.warn(error);
                }
            } finally {
                this.inFlight = false;
            }
        },

        applyPending() {
            if (!this.pending) {
                return;
            }

            dependencies.reload();
        },

        handleVisibilityChange() {
            if (dependencies.visibility() === 'visible') {
                void this.check(true);
            }
        },

        destroy() {
            this.stopped = true;

            if (this.timerId !== null) {
                dependencies.clearInterval(this.timerId);
                this.timerId = null;
            }

            this.unsubscribe?.();
            this.unsubscribe = null;
        },
    };
}

export function agentConsoleSync(config) {
    return createAgentConsoleSync(config);
}
