function browserDependencies() {
    return {
        fetch: (...args) => window.fetch(...args),
        setInterval: (...args) => window.setInterval(...args),
        clearInterval: (timerId) => window.clearInterval(timerId),
        visibility: () => document.visibilityState,
        toast: (detail) => window.dispatchEvent(new CustomEvent('toast', { detail })),
    };
}

export function createAgentConsoleSync(config, dependencies = browserDependencies()) {
    return {
        url: config.url,
        signature: config.signature,
        intervalMs: (config.intervalSeconds ?? 5) * 1000,
        timerId: null,
        clockTimerId: null,
        inFlight: false,
        stopped: false,
        unsubscribe: null,
        agentStatus: config.initialState?.agent_status ?? 'offline',
        statusElapsedSeconds: Number(config.initialState?.status_elapsed_seconds ?? 0),
        call: config.initialState?.call ?? null,
        callActive: Boolean(config.initialState?.call_active),
        callStatus: config.initialState?.call_status ?? null,

        start() {
            this.applyState(config.initialState ?? {});
            this.unsubscribe = config.subscribe?.(() => this.check(true)) ?? null;
            this.timerId = dependencies.setInterval(() => this.check(), this.intervalMs);
            this.clockTimerId = dependencies.setInterval(() => {
                if (this.agentStatus !== 'offline') this.statusElapsedSeconds += 1;
            }, 1000);
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
                this.applyState(state);
            } catch (error) {
                if (import.meta.env?.DEV) {
                    console.warn(error);
                }
            } finally {
                this.inFlight = false;
            }
        },

        applyState(state) {
            if (!state || typeof state !== 'object') {
                return;
            }

            this.signature = state.signature ?? this.signature;
            this.agentStatus = state.agent_status ?? this.agentStatus;
            this.statusElapsedSeconds = this.agentStatus === 'offline'
                ? 0
                : Number(state.status_elapsed_seconds ?? this.statusElapsedSeconds);
            this.callActive = Boolean(state.call_active);
            this.callStatus = state.call_status ?? null;
            this.call = state.call ?? null;

            const softphone = globalThis.Alpine?.store?.('softphone');
            if (softphone) {
                softphone.callActive = this.callActive;
                softphone.callStatus = this.callStatus;
            }
        },

        async setAgentStatus(status) {
            if (!config.statusUrl || this.inFlight) {
                return;
            }

            this.inFlight = true;

            try {
                const response = await dependencies.fetch(config.statusUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ status }),
                });

                const state = await response.json();
                if (!response.ok) {
                    dependencies.toast({
                        title: 'Status não atualizado',
                        message: state.message ?? 'Não foi possível atualizar seu status agora.',
                        severity: 'error',
                    });
                    return;
                }

                this.applyState(state);
            } catch {
                dependencies.toast({
                    title: 'Status não atualizado',
                    message: 'Verifique sua conexão e tente novamente.',
                    severity: 'error',
                });
            } finally {
                this.inFlight = false;
            }
        },

        statusLabel() {
            return {
                available: 'Disponivel',
                dialing: 'Discando',
                paused: 'Em pausa',
                in_call: 'Em chamada',
                wrap_up: 'Em tabulacao',
                offline: 'Offline',
            }[this.agentStatus] ?? 'Offline';
        },

        statusColor() {
            return {
                available: 'var(--status-success)',
                dialing: 'var(--status-info)',
                paused: 'var(--status-warning)',
                in_call: 'var(--primary)',
                wrap_up: 'var(--status-purple)',
                offline: 'var(--fg-muted)',
            }[this.agentStatus] ?? 'var(--fg-muted)';
        },

        isCurrentStatus(status) {
            return this.agentStatus === status;
        },

        pauseActionLabel() {
            return this.isCurrentStatus('paused') ? 'Alterar pausa' : 'Pausar';
        },

        formatDuration(value) {
            const hours = Math.floor(value / 3600);
            const minutes = Math.floor((value % 3600) / 60);
            const seconds = value % 60;

            return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
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

            if (this.clockTimerId !== null) {
                dependencies.clearInterval(this.clockTimerId);
                this.clockTimerId = null;
            }

            this.unsubscribe?.();
            this.unsubscribe = null;
        },
    };
}

export function agentConsoleSync(config) {
    return createAgentConsoleSync(config);
}
