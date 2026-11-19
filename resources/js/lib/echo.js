import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo = null;

/**
 * Lazily create a single Echo instance for the page. Returns null when Reverb
 * has not been configured (for example during a static build without env).
 */
export function getEcho() {
    if (echo) return echo;
    if (typeof window === 'undefined') return null;

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) return null;

    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    return echo;
}
