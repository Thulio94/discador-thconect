import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

const host = import.meta.env.VITE_REVERB_HOST;
const port = Number(import.meta.env.VITE_REVERB_PORT || 80);
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

if (host && import.meta.env.VITE_REVERB_APP_KEY) {
    window.Pusher = Pusher;
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
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
