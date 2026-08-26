import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

const appKey = import.meta.env.VITE_REVERB_APP_KEY
    || document.querySelector('meta[name="reverb-key"]')?.content;
const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
const port = Number(import.meta.env.VITE_REVERB_PORT || window.location.port || (scheme === 'https' ? 443 : 80));

if (host && appKey) {
    window.Pusher = Pusher;
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                    .then((response) => callback(false, response.data))
                    .catch((error) => callback(true, error));
            },
        }),
    });
}

export function subscribeToSupervision(companyId, onPresenceUpdated) {
    if (!window.Echo || !companyId) {
        return () => {};
    }

    const channelName = `tenant.${companyId}.supervision`;

    window.Echo.private(channelName)
        .listen('.agent.presence.updated', onPresenceUpdated);

    return () => window.Echo.leave(channelName);
}

export function subscribeToAgentConsole(companyId, userId, onUpdated) {
    if (!window.Echo || !companyId || !userId) {
        return () => {};
    }

    const channelName = `tenant.${companyId}.user.${userId}`;

    window.Echo.private(channelName)
        .listen('.agent.presence.updated', onUpdated)
        .listen('.agent.console.updated', onUpdated);

    return () => window.Echo.leave(channelName);
}
