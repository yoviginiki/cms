#!/usr/bin/env bash
# Two-client collab presence harness. Starts Reverb + a served app against the
# disposable test DB, seeds fixtures, and runs the two-client Playwright spec.
# Never uses migrate:fresh (plain `migrate` only).
set -uo pipefail
cd "$(dirname "$0")/.."

export DB_DATABASE=cms_saas_platform_test
export SESSION_DRIVER=file              # robust for the harness (no redis dep)
export SESSION_SECURE_COOKIE=false     # harness runs over plain HTTP
export SESSION_SAME_SITE=lax
export SESSION_DOMAIN=                  # host-only cookie (.env pins sys.ensodo.eu)
export APP_URL=http://127.0.0.1:8000
export SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000,localhost:8000
export BROADCAST_CONNECTION=reverb
export REVERB_APP_ID=harness
export REVERB_APP_KEY=smokekey
export REVERB_APP_SECRET=harnesssecret
export REVERB_HOST=127.0.0.1
export REVERB_PORT=9099
export REVERB_SCHEME=http

REVERB_PID=""; SERVE_PID=""
cleanup() { kill "$REVERB_PID" "$SERVE_PID" 2>/dev/null || true; }
trap cleanup EXIT

php artisan config:clear >/dev/null 2>&1 || true
php artisan migrate --force >/dev/null 2>&1 || true   # schema up (NOT fresh)
php collab-harness/seed.php || { echo "seed failed"; exit 1; }

php artisan reverb:start --host=127.0.0.1 --port=9099 >/tmp/harness-reverb.log 2>&1 &
REVERB_PID=$!
php artisan serve --host=127.0.0.1 --port=8000 >/tmp/harness-serve.log 2>&1 &
SERVE_PID=$!

# wait for both ports
for i in $(seq 1 20); do
  (curl -sf -o /dev/null http://127.0.0.1:8000/up 2>/dev/null) && break
  sleep 0.5
done

node collab-harness/presence.spec.mjs
RC=$?

if [ $RC -ne 0 ]; then
  echo "--- reverb log ---"; tail -8 /tmp/harness-reverb.log 2>/dev/null
  echo "--- serve log ---";  tail -12 /tmp/harness-serve.log 2>/dev/null
fi
exit $RC
