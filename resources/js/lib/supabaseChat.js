import { createClient } from '@supabase/supabase-js';

/**
 * Subscribe to the current user's Supabase Realtime broadcast topic.
 * Returns a cleanup function that removes the channel.
 */
export function subscribeToChat({ url, key, topic, handlers }) {
    if (!url || !key || !topic) {
        return () => {};
    }

    const client = createClient(url, key, {
        auth: {
            persistSession: false,
            autoRefreshToken: false,
            detectSessionInUrl: false,
        },
    });

    const channel = client.channel(topic);

    Object.entries(handlers).forEach(([event, handler]) => {
        channel.on('broadcast', { event }, ({ payload }) => handler(payload));
    });

    channel.subscribe();

    return () => {
        client.removeChannel(channel);
    };
}
