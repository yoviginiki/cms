# CMS Platform — agent guide

- **Design recreation / site migration / wizard-fidelity tasks**: READ
  `docs/DESIGN-RECREATION-METHOD.md` FIRST. It is the canonical, validated
  playbook (steps, iteration order, tools, gotchas) — do not rebuild the
  process from scratch and do not "improve" pages by hand: fixes belong in
  the extractors/compilers/blocks so the next site inherits them.
- Platform rule: entrance animations are **scroll-triggered by default** on
  published pages (pinned by `tests/Feature/Publishing/ScrollEntranceAnimationTest`).
- Deploys are atomic (symlink swap): anything writing runtime files at
  publish time must use `AssetPublisher::deployTarget()`, never the public path.
- Blade edits need `php artisan view:clear` + `php artisan queue:restart`
  before republishing, or the worker renders stale compiled views.
- CLI/tinker DB access is RLS-gated: run
  `SELECT set_config('app.current_tenant_id', '<tenant-uuid>', false)` first.
