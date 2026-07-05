#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
# Prune stale hashed admin-bundle chunks.
#
# vite builds with emptyOutDir:false so open admin tabs survive deploys —
# the cost is that public/admin-assets/assets accumulates every historical
# chunk forever (observed: 2,400+ files / 72MB with ~90 live).
#
# Deletes chunks that are BOTH (a) not referenced by the current
# .vite/manifest.json and (b) older than GRACE_DAYS (default 7) — so any
# tab opened within the grace window keeps its lazy chunks loadable.
#
# Usage: scripts/prune-admin-assets.sh [--dry-run]   (run from repo root)
# ═══════════════════════════════════════════════════════════════════════════
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ASSETS="$ROOT/public/admin-assets/assets"
MANIFEST="$ROOT/public/admin-assets/.vite/manifest.json"
GRACE_DAYS="${GRACE_DAYS:-7}"
DRY="${1:-}"

[ -f "$MANIFEST" ] || { echo "no manifest at $MANIFEST"; exit 1; }

KEEP=$(python3 - "$MANIFEST" <<'PY'
import json, sys
m = json.load(open(sys.argv[1]))
keep = set()
for e in m.values():
    if e.get('file'): keep.add(e['file'].split('/')[-1])
    for k in ('css', 'assets'):
        for f in e.get(k, []):
            keep.add(f.split('/')[-1])
print('\n'.join(sorted(keep)))
PY
)

deleted=0; kept=0; bytes=0
while IFS= read -r f; do
    base="$(basename "$f")"
    if grep -qxF "$base" <<< "$KEEP"; then kept=$((kept+1)); continue; fi
    # recent = possibly still loaded by an open tab from a recent deploy
    if [ -n "$(find "$f" -mtime -"$GRACE_DAYS" 2>/dev/null)" ]; then kept=$((kept+1)); continue; fi
    bytes=$((bytes + $(stat -c%s "$f")))
    if [ "$DRY" = "--dry-run" ]; then echo "would delete: $base"; else rm -f "$f"; fi
    deleted=$((deleted+1))
done < <(find "$ASSETS" -type f)

echo "pruned: $deleted files ($((bytes / 1024 / 1024))MB), kept: $kept (manifest-referenced or < ${GRACE_DAYS}d old)"
