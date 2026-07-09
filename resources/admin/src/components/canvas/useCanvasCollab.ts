import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getEcho } from '@/lib/echo';
import { useCanvasStore } from '@/stores/canvasStore';
import { lwwNewer, opKeys, isStampedOp } from '@/lib/collabOps';
import type { CanvasOp, StampedOp } from '@/types/canvas';

export interface PresenceMember {
  id: string;
  name: string;
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
const COALESCE_MS = 700;      // a run of drag-layout ops within this gap = one undo entry

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
  onReseed?: () => void,
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

  // Per-client undo: my own ops with their inverses; consecutive drag layout ops
  // for the same element coalesce into one entry.
  const myUndo = useRef<Array<{ forward: CanvasOp[]; inverse: CanvasOp[] }>>([]);
  const myRedo = useRef<Array<{ forward: CanvasOp[]; inverse: CanvasOp[] }>>([]);
  const coalesce = useRef<{ key: string | null; at: number }>({ key: null, at: 0 });
  const [undoLen, setUndoLen] = useState(0);
  const [redoLen, setRedoLen] = useState(0);

  const isDirty = useCanvasStore(s => s.isDirty);

  // Accept an incoming op under LWW for its primary key; delete always wins.
  const lwwAccept = useCallback((s: StampedOp): boolean => lwwNewer(lww.current.get(opKeys(s.op)[0]), s), []);
  const record = useCallback((s: StampedOp) => {
    for (const k of opKeys(s.op)) lww.current.set(k, { lamport: s.lamport, client: s.client });
  }, []);

  // Stamp an op and broadcast it (LWW-recorded). Layout ops throttle per element
  // during drags; undo/redo ops go out immediately (throttle=false).
  const emitStamped = useCallback((op: CanvasOp, throttleLayout: boolean) => {
    lamport.current += 1;
    const stamped: StampedOp = { op, lamport: lamport.current, client: clientId.current };
    record(stamped);
    if (throttleLayout && op.t === 'layout') {
      const now = Date.now();
      const last = opThrottle.current.get(op.id) ?? 0;
      if (now - last < OP_INTERVAL) return;
      opThrottle.current.set(op.id, now);
    }
    channelRef.current?.whisper('op', stamped);
  }, [record]);

  const pushUndo = useCallback((op: CanvasOp, inverse: CanvasOp[]) => {
    const now = Date.now();
    const top = myUndo.current[myUndo.current.length - 1];
    if (op.t === 'layout' && top && coalesce.current.key === op.id && now - coalesce.current.at < COALESCE_MS) {
      top.forward = [op];               // coalesce a drag into one entry; keep the pre-drag inverse
      coalesce.current.at = now;
    } else {
      myUndo.current.push({ forward: [op], inverse });
      myRedo.current = [];
      coalesce.current = { key: op.t === 'layout' ? op.id : null, at: now };
      setUndoLen(myUndo.current.length);
      setRedoLen(0);
    }
  }, []);

  const undo = useCallback(() => {
    const entry = myUndo.current.pop();
    if (!entry) return;
    entry.inverse.forEach((op) => { useCanvasStore.getState().applyOp(op); emitStamped(op, false); });
    myRedo.current.push(entry);
    coalesce.current = { key: null, at: 0 };
    setUndoLen(myUndo.current.length);
    setRedoLen(myRedo.current.length);
  }, [emitStamped]);

  const redo = useCallback(() => {
    const entry = myRedo.current.pop();
    if (!entry) return;
    entry.forward.forEach((op) => { useCanvasStore.getState().applyOp(op); emitStamped(op, false); });
    myUndo.current.push(entry);
    coalesce.current = { key: null, at: 0 };
    setUndoLen(myUndo.current.length);
    setRedoLen(myRedo.current.length);
  }, [emitStamped]);

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
      if (!d || typeof d.id !== 'string' || d.id === selfId) return;
      if (!Number.isFinite(d.x) || !Number.isFinite(d.y)) return;   // reject garbage coords
      setCursors((c) => ({ ...c, [d.id]: { id: d.id, sectionId: String(d.sectionId ?? ''), x: d.x, y: d.y, at: Date.now() } }));
    });

    ch.listenForWhisper('op', (s: StampedOp) => {
      // Reject malformed peer payloads before they reach applyOp/opKeys, and
      // keep a poisoned (NaN/undefined) lamport from breaking LWW ordering.
      if (!isStampedOp(s) || s.client === clientId.current) return;
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

    // Reconnect → reseed from the last saved tree (we may have missed ops while
    // disconnected). Only fires on a real re-connect, not the initial connect.
    const conn = (echo as unknown as { connector?: { pusher?: { connection?: { bind: (e: string, cb: (s: { previous: string; current: string }) => void) => void; unbind: (e: string, cb: unknown) => void } } } }).connector?.pusher?.connection;
    const onStateChange = (s: { previous: string; current: string }) => {
      if (s.current === 'connected' && (s.previous === 'unavailable' || s.previous === 'disconnected')) {
        lww.current.clear();
        onReseed?.();
      }
    };
    conn?.bind('state_change', onStateChange);

    // Local mutations → broadcast (throttled) + record for per-client undo.
    useCanvasStore.getState().setLocalOpSink((op: CanvasOp, inverse: CanvasOp[]) => {
      emitStamped(op, true);
      pushUndo(op, inverse);
    });

    return () => {
      useCanvasStore.getState().setLocalOpSink(null);
      conn?.unbind('state_change', onStateChange);
      echo.leave(name);
      channelRef.current = null;
      setMembers([]); setCursors({}); setLocks({});
      lww.current.clear(); opThrottle.current.clear();
      myUndo.current = []; myRedo.current = []; setUndoLen(0); setRedoLen(0);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

  return {
    members, cursors, broadcastCursor, lockedIds, isLeader,
    // Per-client op-inverse undo (converges — the inverse is broadcast).
    undo, redo, canUndo: undoLen > 0, canRedo: redoLen > 0,
  };
}
