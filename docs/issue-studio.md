# Issue Studio

Issue Studio is the conversational magazine-creation wizard (admin sidebar → **Issue
Studio**, or the "AI compose issue" button on the Magazines page). It replaced the legacy
Issue Composer wizard in July 2026. You chat with an editorial director; it does the
editing work.

## How it works

1. **Interview** — a short chat (Sonnet). Say what the magazine is about in one sentence;
   the wizard proposes a title, tone, audience and genre itself and asks at most one easy
   question at a time. Paste whole articles straight into the chat (they're filed as
   materials automatically) and drag images anywhere onto it (stored via the normal asset
   pipeline). Say "just do it" whenever you're ready.
2. **Flatplan** — the wizard (Opus) plans the whole issue as a grid of schematic spread
   thumbnails, sized honestly from your material. Drag to reorder (cover and closer are
   pinned), revise any slot in plain language, or approve to lock it.
3. **Spreads** — one spread at a time, in order. Each is generated as a real Magazine
   editor document and previewed through the actual static render (Blade in an iframe).
   For every spread you choose **Keep**, **Revise** ("bigger image, less text"), or
   **Rethink** (fresh take, optionally with a suggested alternative pattern). The wizard
   explains its choices in a one-line editorial note.
4. **Done** — when every spread is kept the session completes and links into the Magazine
   editor for hand-editing. Publishing uses the normal staged-publish path; the wizard
   never publishes.

## Where the judgment lives

Editorial craft is written prose, not code: `resources/playbook/` holds the universal
craft rules, five genre playbooks (politics, art-culture, business, lifestyle, interview),
the flatplanning logic, and the spread-pattern vocabulary (`spread-patterns.md` — the
validator parses its headings, so playbook and code cannot drift). Adding a genre means
writing a markdown file.

## Configuration

- `ANTHROPIC_API_KEY` (shared with the AI content assistant), models via
  `ISSUE_STUDIO_MODEL_INTERVIEW` / `ISSUE_STUDIO_MODEL_GENERATE`.
- Per-tenant monthly token budgets: `tenants.monthly_token_budget` (0 = unlimited);
  usage logged per call on the session (`token_usage`).
- Routes are admin/owner-gated; the preview iframe needs the DTP designer flag.

## Endpoints

See [API-REFERENCE.md](API-REFERENCE.md) → Issue Studio.

## Known v1 limits

Generated text is copy-fitted per frame (no cross-frame threading — thread by hand in the
editor if needed); the preview iframe renders the whole issue; text-on-path, footnotes and
media elements are left to hand-editing.
