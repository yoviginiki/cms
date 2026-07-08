# Collaborative Canvas Editing — Scope (Laravel Reverb)

Status: **scoping / not built.** This is the design + phased plan for real-time
multi-user editing of a canvas page, on top of the shipped canvas editor
(`feature/canvas-editor`). It is deliberately grounded in this repo's actual
state so estimates are real.

> Why this needs a scope, not a headless build: collaboration is only "done"
> when two+ live clients converge correctly. That requires a running Reverb
> process and multi-client testing — neither is verifiable in the CI/headless
> loop the rest of the canvas work used. So this document is the deliverable;
> implementation follows once the decisions below are made.

---

## 1. Current state (what exists vs. what's missing)

| Piece | State | Note |
|---|---|---|
| `laravel/reverb` | **absent** | not in `composer.json` |
| `config/broadcasting.php` | **absent** | `BROADCAST_CONNECTION=null` in `.env` |
| `routes/channels.php` | assume absent/empty | no `Broadcast::channel` registrations found |
| Frontend `laravel-echo` / `pusher-js` | **absent** | admin has no realtime client |
| Existing broadcast event | `app/Events/DeploymentProgressEvent.php` (`ShouldBroadcast`) | scaffolded but inert (driver null); good reference for wiring |
| Queue | `QUEUE_CONNECTION=redis` | broadcasts must NOT go through the async queue for editor ops (latency) — use `ShouldBroadcastNow` or a dedicated sync path |
| Tenancy | RLS via `app.current_tenant_id` GUC; auth is Sanctum SPA (cookie) | **channel auth must set the tenant GUC and enforce the page policy** |
| Canvas persistence | `BlockService::syncBlocks` (lossless, id-preserving) | the authoritative save path already exists and round-trips |
| Canvas store | `canvasStore` zustand, op-shaped actions (`updateElementLayout`, `addElement`, `deleteElements`, z-order, section ops, undo) | ops are already discrete — ideal for broadcasting as deltas |

**Implication:** the client op model is already delta-shaped (every mutation is a
small action), and the server already has a lossless authoritative save. The
missing piece is purely the transport + presence + convergence layer.

---

## 2. Architecture

```
 Admin SPA (React)                Reverb (WS)                 Laravel (HTTP)
 ┌───────────────┐   op delta    ┌──────────┐   fan-out      ┌──────────────┐
 │ canvasStore   ├──────────────▶│ presence │───────────────▶│ (relay only) │
 │  (optimistic) │◀──────────────┤ channel  │◀── auth ───────┤ channels.php │
 │ Echo client   │  peer deltas  └──────────┘                │ + policy+GUC │
 └──────┬────────┘                                           └──────┬───────┘
        │ debounced authoritative save (HTTP)  ── syncBlocks ───────┘
        ▼
   getBlockTree on join → seed store → then apply live deltas
```

- **Transport:** Reverb (first-party, self-hosted WS). One long-running process
  (`php artisan reverb:start`) under supervisor/systemd.
- **Channel:** one **presence** channel per editable page:
  `presence-canvas.page.{pageId}`. Presence gives us the member roster for free
  (who's editing) and per-member metadata (name, color, avatar).
- **Two message classes:**
  - **Ephemeral** (cursors, selection, "grabbing element X") — client→client via
    Reverb **client events** (whisper). Never persisted, never hit Laravel.
  - **Ops** (element moved/resized/added/deleted, section changes) — broadcast as
    small deltas; peers apply optimistically. Persistence is separate (below).
- **Persistence / authority:** the server is a **relay**, not the source of
  truth mid-session. One client is the **save leader** (lowest connection id, or
  server-assigned on presence join); it debounces (~1.5s idle / ~5s max) and
  calls the existing `syncBlocks`. Leadership reassigns on leader disconnect.
  This avoids N clients racing to write the same tree.
- **Join / late-join:** on channel subscribe, the client does one HTTP
  `getBlockTree` to seed `canvasStore`, THEN starts applying buffered live
  deltas (buffer deltas received during the fetch; apply after seed).

---

## 3. Convergence model — LWW + soft locks (recommend), not CRDT

A positioning editor does not need a text-CRDT. Elements are independent objects
keyed by stable id; the only real conflict is "two people move the same element."

- **Per-element Last-Writer-Wins.** Each op carries `{elementId, patch, lamport}`
  (a per-client Lamport counter + clientId tiebreak). Applying an op with a lower
  lamport than the last applied for that element is ignored. Converges without a
  central sequencer.
- **Soft locks (UX, not correctness).** On drag/resize start, whisper a
  `lock {elementId}`; peers show it as busy (dimmed handles, owner's color) and
  suppress their own grab. Lock auto-expires on pointerup / timeout / disconnect.
  Prevents the jarring "two people drag the same box" case without hard locking.
- **Structural ops** (add/delete element, add/reorder/delete section) are also
  LWW by id; delete always wins over a concurrent move (tombstone the id for the
  session so a late move can't resurrect it).
- **Undo** becomes **per-client** (undo only your own ops). Global undo is a
  known-hard problem; per-client undo is the pragmatic, expected behavior and
  fits the existing snapshot stack if scoped to local ops.

Trade-off documented: LWW can drop a simultaneous edit to the *same* element
(the later write wins); soft locks make that rare. If the product later needs
field-level merge, the op format is forward-compatible with a CRDT swap.

---

## 4. Security (must-haves, not optional)

- Channel auth callback for `presence-canvas.page.{pageId}` must:
  1. resolve the authenticated Sanctum user,
  2. **set `app.current_tenant_id`** for the request (RLS) — the page lookup and
     policy check must run tenant-scoped, exactly like HTTP,
  3. authorize `update` on the Page via the existing policy,
  4. return only safe presence metadata (name, id, assigned color) — never email
     or role.
- Reverb app credentials (`REVERB_APP_KEY/SECRET/ID`) per environment; TLS via
  the existing reverse proxy (`wss://`).
- Client events (whispers) are only allowed on presence/private channels and only
  after auth — cursors can't be spoofed cross-tenant because the channel name is
  the page id and auth already gated tenant + policy.
- Rate-limit whispers client-side (cursors throttled to ~30–50ms) to protect the
  Reverb process.

---

## 5. Phases (each independently shippable)

| Phase | Scope | Risk | Est. |
|---|---|---|---|
| **0 — Infra** | Install `laravel/reverb`; `config/broadcasting.php`; `.env` (`BROADCAST_CONNECTION=reverb` + `REVERB_*`); `laravel-echo`+`pusher-js` in admin; Echo bootstrap with Sanctum cookie auth; `routes/channels.php` with the tenant-scoped presence auth; supervisor entry + deploy docs. Prove with a trivial ping event. | Med (ops/infra, tenant-safe auth) | 2–3 d |
| **1 — Presence** | `presence-canvas.page.{id}`; show live editor avatars/roster in the canvas toolbar; assign a per-user color. No shared editing yet. | Low | 1–2 d |
| **2 — Cursors + selection** | Whisper pointer position + current selection; render peers' cursors and selection outlines in their color. Ephemeral only. | Low | 2 d |
| **3 — Live element ops** | Broadcast `updateElementLayout`/`addElement`/`deleteElements`/z-order as LWW deltas; optimistic local apply + peer apply; soft locks on drag/resize; debounced leader autosave via `syncBlocks`; late-join seed. | **High** (convergence, autosave leadership, reconnect) | 4–6 d |
| **4 — Structural + hardening** | Section add/reorder/delete; tombstones; per-client undo; reconnect/replay; conflict edge cases; breakpoint/pin/anim ops. Load/soak test with N clients. | High | 3–5 d |

Phases 0–2 deliver visible value (see-who's-here + cursors) with **no
convergence risk** — a safe first milestone. Phase 3 is where the real work and
the real testing is.

---

## 6. Testing strategy

- **Unit/CI (headless):** op reducer purity (apply(op) is deterministic + LWW
  ordering), lamport compare, tombstone logic, channel-auth policy+tenant
  enforcement (feature test hitting the broadcasting auth endpoint), leader
  election logic.
- **Not CI-coverable (needs the running stack + ≥2 clients):** actual
  convergence, cursor smoothness, reconnect/replay, autosave-under-contention.
  Plan: a Playwright two-context harness (two browser contexts on the same page)
  for the golden paths; manual soak for the rest. **This is why it can't ride the
  same headless-green bar as the rest of canvas.**

---

## 7. Decisions needed before Phase 0

1. **Reverb hosting** — same box as the app (simplest) vs. dedicated? Connection
   ceiling / expected concurrent editors per page?
2. **Autosave authority** — client leader (recommended, simplest) vs. a
   server-side authoritative apply (safer, more work: server becomes the reducer
   and the only writer)?
3. **Undo semantics** — confirm per-client undo is acceptable (vs. no undo in
   collab sessions).
4. **Scope of realtime** — pages only, or posts too? All canvas features
   (breakpoint/pin/anim) realtime in v1, or positioning only first?
5. **Presence identity** — show names+avatars (needs a safe user resource) or
   anonymous colors only?

---

## 8. Ops / cost

- One persistent PHP process (`reverb:start`) per environment, supervised;
  memory scales with concurrent connections (~cheap for editor-scale usage).
- No third-party bill (self-hosted). If we ever outgrow a single box, Reverb
  scales horizontally with a Redis pub/sub backend (already have Redis).
- Published tenant sites are **unaffected** — collaboration is admin-only; the
  static publish pipeline and PageSpeed posture do not change.

---

## 8b. Phase 0 — implementation status (this branch)

**Built + tested here (no Reverb process required):**
- `config/broadcasting.php` — reverb/pusher/log/null connections; default stays
  env-controlled (`null` in CI, `reverb` in prod).
- `routes/channels.php` — presence channel `canvas.page.{pageId}`, delegating to…
- `app/Domain/Collab/CanvasChannelAuthorizer` — tenant-RLS + `update`-policy gate
  returning a **safe** `{id,name,color}` member payload (no email/role).
- `bootstrap/app.php` — `withBroadcasting(...)` wiring the `/broadcasting/auth`
  route through stateful Sanctum + `SetTenantFromAuth` (so the RLS GUC is set
  before the channel callback runs). Verified: route registered, app boots green.
- `tests/Feature/Collab/CanvasChannelAuthTest` — owner joins, same-tenant editor
  joins, **cross-tenant user rejected**, no PII in the payload.

**Now installed + wired on this branch:**
- `laravel/reverb` (composer) and `laravel-echo` + `pusher-js` (admin) are in.
- `src/lib/echo.ts` — lazy Reverb/Echo singleton; **no-op when
  `VITE_REVERB_APP_KEY` is unset**, so the admin runs fine without a collab
  server. Channel auth routes through the existing cookie/XSRF path to
  `/broadcasting/auth`.
- **Phase 1 presence** — `useCanvasPresence` joins `canvas.page.{id}`; the
  CanvasEditor toolbar shows a live editor roster (colored initials). App builds
  green (tsc 0, vite OK, 290 JS tests).

**Remaining to activate (needs a running process + env; run at deploy):**
```
# server .env:
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...   REVERB_APP_KEY=...   REVERB_APP_SECRET=...
REVERB_HOST="sys.ensodo.eu"   REVERB_PORT=443   REVERB_SCHEME=https
# admin build-time (Vite) env — so the client knows where Reverb is:
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST="sys.ensodo.eu"   VITE_REVERB_PORT=443   VITE_REVERB_SCHEME=https
# process manager (supervisor/systemd):
php artisan reverb:start
# reverse proxy: allow the wss upgrade on the reverb port
```
Everything up to the live socket is built and tested; the above turns it on.
Phases 2–4 (cursors, convergent ops) still require the running server + a
two-client harness to develop against.

## 9. Recommendation

Ship **Phase 0–2 first** (infra + presence + cursors): high perceived value,
low risk, fully reviewable, and it proves the Reverb+tenant-auth plumbing before
committing to the hard convergence work. Gate **Phase 3** on decisions #2 and #3.
Keep positioning-only for the first convergent release; add breakpoint/pin/anim
ops in Phase 4 once the core is stable.
