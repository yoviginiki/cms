## Canvas editor + collaborative editing

A third `editor_mode = 'canvas'` for **pages and posts** — a vertical stack of Section canvases with freeform-positioned website blocks — alongside the untouched Block and Magazine editors, **plus real-time multi-user editing** on Laravel Reverb.

**Base:** stacked on `fix/audit-remediation` (needs its block-id-preservation fix for lossless round-trips). Retarget to `master` once that merges.

### Canvas editor
- **No new data format** — sections are `section` blocks; elements are child blocks carrying `style.layout {x,y,w,h,rotation,zIndex}`. Lossless round-trip (`canvasAdapter`), no separate storage.
- Static Blade publish (theme-width / full-bleed sections, absolute children, **mobile auto-stack** in source order); no React in published output.
- **Per-breakpoint mobile layouts**, **per-element pin/anchor** (fluid sections, pure CSS), **scroll-triggered animations** (IntersectionObserver reveal, reduced-motion + no-JS fallbacks).
- **Magazine → canvas duplication** (converter + draft-copy endpoint + editor button).
- Editor: reuses the shared `smartGuides`; drag/resize (rotation-aware) / rotate / multi-select / keyboard-nudge (batched undo); zoom-constant handles; split-pane live preview with a mobile toggle.

### Collaborative editing (Reverb) — Phases 0–4 + per-client undo
- **Infra + tenant-scoped channel auth** (`BroadcastServiceProvider`, RLS GUC + `update` policy, safe presence payload).
- **Presence roster**, **live cursors** (whispers), **convergent element ops** (per-element Last-Writer-Wins + lamport, activity-based soft locks, autosave leader).
- **Reconnect reseed**, **N-client fan-out**, and **per-client op-inverse undo** (reverts only your ops, broadcasts the inverse so peers converge; drag-coalesced).
- No-op without Reverb configured; published tenant sites unaffected. Activation env + `reverb:start` documented in `docs/canvas-collab-scope.md §8b`.

### Verification
- **~303 JS tests + full PHP suite** pass, tsc 0, `vite build` OK, 0 regressions; Magazine/block editors **provably untouched**.
- Playwright: canvas desktop-freeform / 390px-stack / Slow-3G.
- **Two/three-client Reverb harness (`collab-harness/`) 11/11 live** — presence, cursors, op fan-out, undo-inverse propagation.

### Still needs a human
- **Manual feel-test** of the canvas editor (drag ergonomics, snap thresholds).
- **Collab activation** in a real env (`REVERB_*` / `VITE_REVERB_*` + `reverb:start`) to build/verify the interactive collab UI end-to-end.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
