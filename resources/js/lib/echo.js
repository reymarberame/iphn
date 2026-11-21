import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo = null;

/**
 * Lazily create a single Echo instance for the page. Reverb settings come from
 * Inertia at runtime; Vite env values remain as a fallback for static builds.
 * Returns null when Reverb has not been configured.
 */
export function getEcho(config = null) {
    if (echo) return echo;
    if (typeof window === 'undefined') return null;

    const key = config?.key || import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) return null;

    window.Pusher = Pusher;

    const scheme =
        config?.scheme
        || import.meta.env.VITE_REVERB_SCHEME
        || (window.location.protocol === 'https:' ? 'https' : 'http');
    const host = config?.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = Number(
        config?.port
        || import.meta.env.VITE_REVERB_PORT
        || (scheme === 'https' ? 443 : 80),
    );

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    return echo;
}
