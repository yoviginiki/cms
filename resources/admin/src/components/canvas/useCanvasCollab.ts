import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho } from '@/lib/echo';

export interface PresenceMember {
  id: string;
  name: string;
  color: string;
}

export interface PeerCursor {
  id: string;
  sectionId: string;
  x: number;
  y: number;
  at: number;
}

const CURSOR_INTERVAL = 45;   // ms between cursor whispers
const CURSOR_TTL = 5000;      // drop a peer cursor after this idle

/**
 * Canvas collaboration (Phase 1 presence + Phase 2 cursors). Joins the
 * `canvas.page.{id}` presence channel (tenant-gated server-side), exposes the
 * live member roster, and relays cursor positions as ephemeral client whispers
 * (no persistence, no Laravel round-trip). No-op when Reverb isn't configured.
 */
export function useCanvasCollab(pageId: string, contentType: 'pages' | 'posts', selfId: string | undefined) {
  const [members, setMembers] = useState<PresenceMember[]>([]);
  const [cursors, setCursors] = useState<Record<string, PeerCursor>>({});
  const channelRef = useRef<ReturnType<NonNullable<ReturnType<typeof getEcho>>['join']> | null>(null);
  const lastSent = useRef(0);

  useEffect(() => {
    if (contentType !== 'pages' || !pageId) return;
    const echo = getEcho();
    if (!echo) return;

    const name = `canvas.page.${pageId}`;
    const ch = echo.join(name)
      .here((users: PresenceMember[]) => setMembers(users))
      .joining((u: PresenceMember) => setMembers((m) => [...m.filter((x) => x.id !== u.id), u]))
      .leaving((u: PresenceMember) => {
        setMembers((m) => m.filter((x) => x.id !== u.id));
        setCursors((c) => { const { [u.id]: _drop, ...rest } = c; return rest; });
      })
      .error(() => setMembers([]));

    ch.listenForWhisper('cursor', (data: { id: string; sectionId: string; x: number; y: number }) => {
      if (!data || data.id === selfId) return;
      setCursors((c) => ({ ...c, [data.id]: { ...data, at: Date.now() } }));
    });

    channelRef.current = ch;
    return () => {
      echo.leave(name);
      channelRef.current = null;
      setMembers([]);
      setCursors({});
    };
  }, [pageId, contentType, selfId]);

  // Drop cursors of peers who stopped moving (or left/hid the tab).
  useEffect(() => {
    const t = window.setInterval(() => {
      setCursors((c) => {
        const now = Date.now();
        let changed = false;
        const next: Record<string, PeerCursor> = {};
        for (const k in c) { if (now - c[k].at < CURSOR_TTL) next[k] = c[k]; else changed = true; }
        return changed ? next : c;
      });
    }, 2000);
    return () => window.clearInterval(t);
  }, []);

  const broadcastCursor = useCallback((sectionId: string, x: number, y: number) => {
    if (!selfId) return;
    const now = Date.now();
    if (now - lastSent.current < CURSOR_INTERVAL) return;
    lastSent.current = now;
    channelRef.current?.whisper('cursor', { id: selfId, sectionId, x: Math.round(x), y: Math.round(y) });
  }, [selfId]);

  return { members, cursors, broadcastCursor };
}
