import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

// Lazy Reverb/Echo singleton. Returns null when collaboration isn't configured
// (no VITE_REVERB_APP_KEY) so the admin runs perfectly fine without a Reverb
// server — presence/cursor features simply stay dormant.
let instance: Echo<'reverb'> | null | undefined;

// import.meta.env without requiring vite/client types in tsconfig.
const env = ((import.meta as unknown as { env?: Record<string, string | undefined> }).env) ?? {};

export function getEcho(): Echo<'reverb'> | null {
  if (instance !== undefined) return instance;

  const key = env.VITE_REVERB_APP_KEY;
  if (!key) {
    instance = null;
    return null;
  }

  (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

  instance = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key,
    wsHost: env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(env.VITE_REVERB_PORT) || 443,
    wssPort: Number(env.VITE_REVERB_PORT) || 443,
    forceTLS: (env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Route channel auth through the same cookie/XSRF path as the rest of the
    // admin, hitting the tenant-scoped /broadcasting/auth endpoint.
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: boolean, data: unknown) => void) => {
        axios
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name }, { withCredentials: true })
          .then((res) => callback(false, res.data))
          .catch((err) => {
            // Surface the cause in dev — a silent reject makes a misconfigured
            // Reverb / CSRF / RLS-denied channel very hard to diagnose.
            if (env.DEV) console.error('[canvas-collab] channel auth failed for', channel.name, err);
            callback(true, {});
          });
      },
    }),
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } as any);

  return instance;
}

export function isCollabEnabled(): boolean {
  return !!env.VITE_REVERB_APP_KEY;
}
