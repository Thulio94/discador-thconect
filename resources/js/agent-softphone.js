function defaultDependencies() {
    return {
        fetch: (...args) => window.fetch(...args),
        getUserMedia: (constraints) => navigator.mediaDevices.getUserMedia(constraints),
        loadJsSIP: async () => {
            const module = await import('jssip');
            return module.default ?? module;
        },
        attachRemoteStream: async (stream) => {
            const audio = document.querySelector('[data-agent-remote-audio]');
            if (!audio) return;
            audio.srcObject = stream;
            await audio.play();
        },
        toast: (detail) => window.dispatchEvent(new CustomEvent('toast', { detail })),
        setTimeout: (...args) => window.setTimeout(...args),
        clearTimeout: (timer) => window.clearTimeout(timer),
        setInterval: (...args) => window.setInterval(...args),
        clearInterval: (timer) => window.clearInterval(timer),
        markOffline: (url, csrfToken) => window.fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ status: 'offline' }),
        }),
        reload: () => window.location.reload(),
    };
}

export function createAgentSoftphone(config, dependencies = defaultDependencies()) {
    return {
        state: 'preparing',
        registered: false,
        ua: null,
        activeSession: null,
        iceServers: [],
        failureTimer: null,
        renewalTimer: null,
        stopped: false,

        async start() {
            if (this.stopped || !globalThis.RTCPeerConnection || !globalThis.WebSocket) {
                this.setState('unsupported');
                return;
            }

            try {
                const permissionStream = await dependencies.getUserMedia({ audio: true, video: false });
                permissionStream.getTracks().forEach((track) => track.stop());

                const credentials = await this.requestCredentials();
                this.iceServers = credentials.ice_servers ?? [];
                const JsSIP = await dependencies.loadJsSIP();
                const socket = new JsSIP.WebSocketInterface(credentials.websocket_url);
                this.ua = new JsSIP.UA({
                    sockets: [socket],
                    uri: credentials.uri,
                    authorization_user: credentials.endpoint,
                    password: credentials.password,
                    register: true,
                    register_expires: 120,
                    connection_recovery_min_interval: 2,
                    connection_recovery_max_interval: 30,
                    session_timers: false,
                });

                this.ua.on('registered', () => {
                    this.cancelFailure();
                    this.registered = true;
                    this.setState('registered');
                });
                this.ua.on('unregistered', () => {
                    this.registered = false;
                    this.setState(this.stopped ? 'offline' : 'reconnecting');
                });
                this.ua.on('disconnected', () => this.scheduleFailure());
                this.ua.on('registrationFailed', () => this.scheduleFailure());
                this.ua.on('newRTCSession', (event) => this.handleSession(event));
                this.ua.start();
                this.renewalTimer = dependencies.setInterval(() => this.renew(), 60000);
            } catch (error) {
                this.registered = false;
                this.setState(error?.name === 'NotAllowedError' ? 'microphone-denied' : 'failed');
                dependencies.toast({
                    title: 'Audio indisponivel',
                    message: 'Libere o microfone e confira a conexao para receber chamadas.',
                    severity: 'error',
                });
            }
        },

        async requestCredentials() {
            const response = await dependencies.fetch(config.sessionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '{}',
            });
            if (!response.ok) {
                throw new Error(`Softphone session failed with ${response.status}`);
            }
            return response.json();
        },

        async renew() {
            if (this.stopped) return;
            try {
                await this.requestCredentials();
            } catch {
                this.scheduleFailure();
            }
        },

        handleSession(event) {
            if (event.originator !== 'remote') return;
            const session = event.session;

            if (!config.acceptIncoming || this.activeSession) {
                session.terminate({ status_code: 486, reason_phrase: 'Agent unavailable' });
                return;
            }

            this.activeSession = session;
            this.syncActiveSession();
            session.on('peerconnection', ({ peerconnection }) => {
                peerconnection.addEventListener('track', ({ streams }) => {
                    if (streams?.[0]) {
                        dependencies.attachRemoteStream(streams[0]).catch(() => {
                            this.setState('playback-blocked');
                        });
                    }
                });
            });
            const clear = () => {
                if (this.activeSession === session) {
                    this.activeSession = null;
                    this.syncActiveSession();
                }
            };
            session.on('ended', clear);
            session.on('failed', clear);
            session.answer({
                mediaConstraints: { audio: true, video: false },
                pcConfig: { iceServers: this.iceServers },
            });
        },

        scheduleFailure() {
            this.registered = false;
            this.setState('reconnecting');
            this.cancelFailure();
            this.failureTimer = dependencies.setTimeout(() => {
                if (!this.registered && !this.stopped) {
                    this.setState('failed');
                    dependencies.toast({
                        title: 'Ramal desconectado',
                        message: 'As chamadas estao bloqueadas ate o audio reconectar.',
                        severity: 'error',
                    });
                    if (config.currentStatus === 'available') {
                        dependencies.markOffline(config.statusUrl, config.csrfToken)
                            .finally(() => dependencies.reload());
                    }
                }
            }, 10000);
        },

        cancelFailure() {
            if (this.failureTimer !== null) {
                dependencies.clearTimeout(this.failureTimer);
                this.failureTimer = null;
            }
        },

        setState(state) {
            this.state = state;
            const store = globalThis.Alpine?.store?.('softphone');
            if (store) {
                store.state = state;
                store.registered = state === 'registered';
            }
        },

        syncActiveSession() {
            const store = globalThis.Alpine?.store?.('softphone');
            if (store) {
                store.activeSession = this.activeSession !== null;
            }
        },

        stop() {
            this.stopped = true;
            this.cancelFailure();
            if (this.renewalTimer !== null) {
                dependencies.clearInterval(this.renewalTimer);
                this.renewalTimer = null;
            }
            this.activeSession?.terminate();
            this.activeSession = null;
            this.syncActiveSession();
            this.ua?.stop();
            this.ua = null;
            this.registered = false;
            this.setState('offline');
        },
    };
}

export function registerAgentSoftphone(Alpine) {
    Alpine.store('softphone', { state: 'preparing', registered: false, activeSession: false, callActive: false, callStatus: null });
    Alpine.data('agentSoftphone', (config) => createAgentSoftphone(config));
}
