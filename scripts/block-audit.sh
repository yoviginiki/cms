#!/bin/bash
#
# Block Audit — thin wrapper around the ONE authoritative audit
# (scripts/audit-blocks.mjs, driven by scripts/block-manifest.json).
# Kept so existing docs/CI invocations (`bash scripts/block-audit.sh`) keep
# working; the shell implementation that lived here derived block types from
# class-file names and disagreed with the Node audit (audit 2026-09-22, F31).
#
# Usage: bash scripts/block-audit.sh [--json-only] [--no-color]
#
set -euo pipefail
BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
exec node "$BASE_DIR/scripts/audit-blocks.mjs" "$@"
