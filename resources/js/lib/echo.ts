import Echo from 'laravel-echo';

let echo: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
    if (typeof window === 'undefined' || !import.meta.env.VITE_REVERB_APP_KEY) {
        return null;
    }

    if (echo === null) {
        echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost:
                import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
            forceTLS:
                (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    return echo;
}
