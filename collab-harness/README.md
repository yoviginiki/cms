# Two-client collaborative-presence harness (collab Phase 1/2)

Validates the Reverb presence channel with **two real authenticated browser
clients** — the thing that can't be checked by unit tests. Proves: tenant-gated
channel auth, live `member_added`/`member_removed`, and the whole
serve → Reverb → cookie-auth → presence path.

## Run

```bash
bash collab-harness/run.sh
```

Expected:

```
PASS  alice joins presence (sees herself)
PASS  bob joins presence (sees both)
PASS  alice observes bob join (live member_added)
PASS  alice sees bob leave (member_removed)
done
```

## What it does

1. `seed.php` — seeds a tenant, two owner users, a site and a canvas page into
   the **disposable test DB** (`cms_saas_platform_test`), writing `fixture.json`.
   (Assumes the schema is migrated; `run.sh` runs plain `migrate` first — never
   `migrate:fresh`.)
2. `run.sh` — starts `reverb:start` + `artisan serve` against the test DB with
   harness env, then runs the spec. Cleans up both processes on exit.
3. `presence.spec.mjs` — Playwright: two browser contexts each log in (Sanctum
   SPA cookie flow), then drive **pusher-js** directly against Reverb (Reverb
   speaks the Pusher protocol) to join `presence-canvas.page.{id}` and assert
   mutual visibility + join/leave events.

## Harness-only env (in run.sh)

Set because the harness runs over plain HTTP on `127.0.0.1`, whereas prod is
HTTPS on `sys.ensodo.eu`:

| var | why |
|---|---|
| `DB_DATABASE=cms_saas_platform_test` | disposable DB, never touches dev/prod |
| `SESSION_DOMAIN=` (empty) | `.env` pins `sys.ensodo.eu`; host-only cookie needed for 127.0.0.1 |
| `SESSION_SECURE_COOKIE=false` | plain HTTP |
| `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000` | first-party session auth |
| `BROADCAST_CONNECTION=reverb` + `REVERB_*` | point serve + client at the local Reverb |

None of these change production config — they're process env for the harness run.

## Extending to Phase 2 (cursors) / Phase 3 (ops)

The same two-context pattern is the dev loop for the rest of collab: add cursor
whispers (client events) and assert peer B sees peer A's cursor move; then op
deltas (LWW) and assert both canvases converge. Build against this harness.
