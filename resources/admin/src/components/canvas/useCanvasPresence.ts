import { useEffect, useState } from 'react';
import { getEcho } from '@/lib/echo';

export interface PresenceMember {
  id: string;
  name: string;
  color: string;
}

/**
 * Phase 1 presence: the live roster of editors on a canvas page. Subscribes to
 * the `canvas.page.{id}` presence channel (auth is the tenant+policy gate on the
 * server). No-op — returns [] — when Reverb isn't configured, so the editor is
 * unaffected without a collab server.
 */
export function useCanvasPresence(pageId: string, contentType: 'pages' | 'posts', enabled = true): PresenceMember[] {
  const [members, setMembers] = useState<PresenceMember[]>([]);

  useEffect(() => {
    // Only pages have the presence channel wired in Phase 1.
    if (!enabled || contentType !== 'pages' || !pageId) return;
    const echo = getEcho();
    if (!echo) return;

    const name = `canvas.page.${pageId}`;
    echo.join(name)
      .here((users: PresenceMember[]) => setMembers(users))
      .joining((user: PresenceMember) => setMembers((m) => [...m.filter((x) => x.id !== user.id), user]))
      .leaving((user: PresenceMember) => setMembers((m) => m.filter((x) => x.id !== user.id)))
      .error(() => setMembers([]));

    return () => {
      echo.leave(name);
      setMembers([]);
    };
  }, [pageId, contentType, enabled]);

  return members;
}
