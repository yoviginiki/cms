import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getEcho } from '@/lib/echo';
import { useCanvasStore } from '@/stores/canvasStore';
import { lwwNewer, opKeys } from '@/lib/collabOps';
import type { CanvasOp, StampedOp } from '@/types/canvas';

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
const OP_INTERVAL = 50;       // ms throttle for layout ops per element
const LOCK_TTL = 2500;        // an element counts as "peer-editing" this long after an op

/**
 * Canvas collaboration: presence (Phase 1), live cursors (Phase 2), and
 * convergent element ops (Phase 3). Element mutations are broadcast as deltas
 * and applied with per-element Last-Writer-Wins (lamport + client tiebreak);
 * the lowest-id member is the autosave leader. All transport is Reverb whispers
 * (ephemeral) except persistence, which goes through the normal syncBlocks save.
 * No-op without Reverb.
 */
export function useCanvasCollab(
  pageId: string,
  contentType: 'pages' | 'posts',
  selfId: string | undefined,
  onAutosave?: () => void,
) {
  const [members, setMembers] = useState<PresenceMember[]>([]);
  const [cursors, setCursors] = useState<Record<string, PeerCursor>>({});
  const [locks, setLocks] = useState<Record<string, { client: string; at: number }>>({});

  const channelRef = useRef<ReturnType<NonNullable<ReturnType<typeof getEcho>>['join']> | null>(null);
  const clientId = useRef<string>((typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
  const lamport = useRef(0);
  const lww = useRef<Map<string, { lamport: number; client: string }>>(new Map());
  const lastCursor = useRef(0);
  const opThrottle = useRef<Map<string, number>>(new Map());

  const isDirty = useCanvasStore(s => s.isDirty);

  // Accept an incoming op under LWW for its primary key; delete always wins.
  const lwwAccept = useCallback((s: StampedOp): boolean => lwwNewer(lww.current.get(opKeys(s.op)[0]), s), []);
  const record = useCallback((s: StampedOp) => {
    for (const k of opKeys(s.op)) lww.current.set(k, { lamport: s.lamport, client: s.client });
  }, []);

  // ── join channel; wire presence + cursor + op streams ──
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
        setCursors((c) => { const { [u.id]: _d, ...rest } = c; return rest; });
      })
      .error(() => setMembers([]));

    ch.listenForWhisper('cursor', (d: { id: string; sectionId: string; x: number; y: number }) => {
      if (!d || d.id === selfId) return;
      setCursors((c) => ({ ...c, [d.id]: { ...d, at: Date.now() } }));
    });

    ch.listenForWhisper('op', (s: StampedOp) => {
      if (!s || s.client === clientId.current) return;
      lamport.current = Math.max(lamport.current, s.lamport);
      if (!lwwAccept(s)) return;             // stale — a newer edit already applied
      record(s);
      useCanvasStore.getState().applyOp(s.op);
      // activity-based soft lock: mark touched elements as peer-editing
      if (s.op.t === 'layout' || s.op.t === 'z') {
        const now = Date.now();
        setLocks((l) => {
          const next = { ...l };
          for (const k of opKeys(s.op)) next[k] = { client: s.client, at: now };
          return next;
        });
      }
    });

    channelRef.current = ch;

    // Local mutations → broadcast (throttle layout ops per element).
    useCanvasStore.getState().setLocalOpSink((op: CanvasOp) => {
      lamport.current += 1;
      const stamped: StampedOp = { op, lamport: lamport.current, client: clientId.current };
      record(stamped);
      if (op.t === 'layout') {
        const now = Date.now();
        const last = opThrottle.current.get(op.id) ?? 0;
        if (now - last < OP_INTERVAL) return;   // coalesce rapid drag deltas
        opThrottle.current.set(op.id, now);
      }
      channelRef.current?.whisper('op', stamped);
    });

    return () => {
      useCanvasStore.getState().setLocalOpSink(null);
      echo.leave(name);
      channelRef.current = null;
      setMembers([]); setCursors({}); setLocks({});
      lww.current.clear(); opThrottle.current.clear();
    };
  }, [pageId, contentType, selfId, lwwAccept, record]);

  // prune idle cursors + stale locks
  useEffect(() => {
    const t = window.setInterval(() => {
      const now = Date.now();
      setCursors((c) => {
        let changed = false; const n: Record<string, PeerCursor> = {};
        for (const k in c) { if (now - c[k].at < CURSOR_TTL) n[k] = c[k]; else changed = true; }
        return changed ? n : c;
      });
      setLocks((l) => {
        let changed = false; const n: Record<string, { client: string; at: number }> = {};
        for (const k in l) { if (now - l[k].at < LOCK_TTL) n[k] = l[k]; else changed = true; }
        return changed ? n : l;
      });
    }, 1000);
    return () => window.clearInterval(t);
  }, []);

  const broadcastCursor = useCallback((sectionId: string, x: number, y: number) => {
    if (!selfId) return;
    const now = Date.now();
    if (now - lastCursor.current < CURSOR_INTERVAL) return;
    lastCursor.current = now;
    channelRef.current?.whisper('cursor', { id: selfId, sectionId, x: Math.round(x), y: Math.round(y) });
  }, [selfId]);

  // ── autosave leader: the lowest-id member persists (debounced) ──
  const isLeader = !!selfId && members.length > 0 && members.every((m) => selfId <= m.id);
  const saveTimer = useRef<number | null>(null);
  useEffect(() => {
    if (!isLeader || !isDirty || !onAutosave) return;
    if (saveTimer.current) window.clearTimeout(saveTimer.current);
    saveTimer.current = window.setTimeout(() => onAutosave(), 1500);
    return () => { if (saveTimer.current) window.clearTimeout(saveTimer.current); };
  }, [isDirty, isLeader, onAutosave]);

  const lockedIds = useMemo(() => new Set(Object.keys(locks).filter((k) => locks[k].client !== clientId.current)), [locks]);

  return { members, cursors, broadcastCursor, lockedIds, isLeader };
}
