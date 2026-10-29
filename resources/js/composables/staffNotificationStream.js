import Pusher from 'pusher-js';

// Configuration is public; the app secret stays on the server.
export function subscribeStaffNotifications({ organizationId, userId, onChange, onSubscribed, onUnavailable }) {
    const meta = document.querySelector('meta[name="notifications-realtime"]');
    const config = meta ? JSON.parse(meta.content) : null;
    if (!config?.key || !organizationId || !userId) {
        onUnavailable();
        return () => {};
    }
    const client = new Pusher(config.key, {
        cluster: 'mt1',
        wsHost: config.host || window.location.hostname,
        wsPort: Number(config.port),
        wssPort: Number(config.port),
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        channelAuthorization: {
            customHandler: ({ socketId, channelName }, callback) => {
                window.axios.post('/broadcasting/auth', { socket_id: socketId, channel_name: channelName })
                    .then(({ data }) => callback(null, data))
                    .catch((error) => callback(error, null));
            },
        },
    });
    const channel = client.subscribe(`private-staff.org.${organizationId}.user.${userId}`);
    channel.bind('staff.notification.changed', (event) => {
        if (Number(event.organizationId) === Number(organizationId) && Number(event.userId) === Number(userId)) onChange(event);
    });
    channel.bind('pusher:subscription_succeeded', onSubscribed);
    channel.bind('pusher:subscription_error', onUnavailable);
    client.connection.bind('state_change', ({ current }) => {
        if (['unavailable', 'failed', 'disconnected'].includes(current)) onUnavailable();
    });
    return () => {
        channel.unbind_all();
        client.disconnect();
    };
}
