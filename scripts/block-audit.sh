#!/bin/bash
#
# Block Audit Script
# Checks consistency across all three layers: Frontend (React) → Backend (PHP) → Rendering (Blade)
#
# Usage: bash scripts/block-audit.sh [--verbose] [--json]
#

set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$BASE_DIR/resources/admin/src/components/blocks"
BLADE_DIR="$BASE_DIR/resources/views/blocks"
PHP_DIR="$BASE_DIR/app/Domain/Blocks/Definitions"
VERBOSE=false
JSON_OUTPUT=false

for arg in "$@"; do
  case $arg in
    --verbose) VERBOSE=true ;;
    --json) JSON_OUTPUT=true ;;
  esac
done

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Counters
TOTAL=0
COMPLETE=0
WARNINGS=0
ERRORS=0

declare -a MISSING_BLADE=()
declare -a MISSING_PHP=()
declare -a MISSING_FRONTEND_FILES=()
declare -a ORPHAN_BLADES=()
declare -a ISSUES=()

echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  Block Audit — Ensodo CMS Platform${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
echo ""

# Get all frontend block folders (excluding .ts files like registry.ts, index.ts)
FRONTEND_BLOCKS=$(ls "$FRONTEND_DIR" | grep -v '\.ts$' | sort)

# Get all blade templates
BLADE_BLOCKS=$(ls "$BLADE_DIR"/*.blade.php 2>/dev/null | xargs -I{} basename {} .blade.php | sort)

# Get all PHP definitions (excluding the interface)
PHP_BLOCKS=$(ls "$PHP_DIR"/*.php 2>/dev/null | xargs -I{} basename {} .php | grep -v '^BlockDefinition$' | sed 's/BlockDefinition$//' | tr '[:upper:]' '[:lower:]' | sort)

# ─── Check each frontend block ───
echo -e "${CYAN}Checking frontend blocks...${NC}"
echo ""

for block in $FRONTEND_BLOCKS; do
  TOTAL=$((TOTAL + 1))
  block_issues=()
  has_error=false

  # Check frontend files
  if [ ! -f "$FRONTEND_DIR/$block/definition.ts" ]; then
    block_issues+=("missing definition.ts")
    has_error=true
  fi
  if [ ! -f "$FRONTEND_DIR/$block/Editor.tsx" ]; then
    block_issues+=("missing Editor.tsx")
    has_error=true
  fi
  if [ ! -f "$FRONTEND_DIR/$block/Preview.tsx" ]; then
    block_issues+=("missing Preview.tsx")
    has_error=true
  fi
  if [ ! -f "$FRONTEND_DIR/$block/index.ts" ]; then
    block_issues+=("missing index.ts")
    has_error=true
  fi

  # Check blade template
  # Handle special cases: scroll_page has subdirectory
  if [ ! -f "$BLADE_DIR/$block.blade.php" ]; then
    block_issues+=("missing blade template")
    MISSING_BLADE+=("$block")
    has_error=true
  fi

  # Check PHP definition
  # Convert block type to PascalCase for class name check
  pascal=$(echo "$block" | sed -r 's/(^|[-_])(\w)/\U\2/g')
  if [ ! -f "$PHP_DIR/${pascal}BlockDefinition.php" ]; then
    block_issues+=("missing PHP definition")
    MISSING_PHP+=("$block")
  fi

  # Report
  if [ ${#block_issues[@]} -eq 0 ]; then
    COMPLETE=$((COMPLETE + 1))
    if $VERBOSE; then
      echo -e "  ${GREEN}✓${NC} $block — complete"
    fi
  elif $has_error; then
    ERRORS=$((ERRORS + 1))
    echo -e "  ${RED}✗${NC} $block: ${block_issues[*]}"
    ISSUES+=("ERROR: $block — ${block_issues[*]}")
  else
    # Only missing PHP definition — frontend+blade are fine
    WARNINGS=$((WARNINGS + 1))
    if $VERBOSE; then
      echo -e "  ${YELLOW}⚠${NC} $block: ${block_issues[*]}"
    fi
  fi
done

# ─── Check for orphan blade templates ───
echo ""
echo -e "${CYAN}Checking for orphan blade templates...${NC}"
for blade in $BLADE_BLOCKS; do
  if [ ! -d "$FRONTEND_DIR/$blade" ]; then
    ORPHAN_BLADES+=("$blade")
    echo -e "  ${YELLOW}⚠${NC} $blade.blade.php — no matching frontend component"
    ISSUES+=("ORPHAN: $blade.blade.php has no frontend")
  fi
done

# ─── Summary ───
echo ""
echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  Summary${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════${NC}"
echo ""
echo -e "  Total frontend blocks:     ${TOTAL}"
echo -e "  Fully complete (all 3):    ${GREEN}${COMPLETE}${NC}"
echo -e "  Warnings (missing PHP):    ${YELLOW}${WARNINGS}${NC}"
echo -e "  Errors (missing files):    ${RED}${ERRORS}${NC}"
echo -e "  Orphan blade templates:    ${YELLOW}${#ORPHAN_BLADES[@]}${NC}"
echo ""
echo -e "  ${CYAN}Layer coverage:${NC}"
echo -e "    Frontend:  ${TOTAL}/${TOTAL} (100%)"
echo -e "    Blade:     $((TOTAL - ${#MISSING_BLADE[@]}))/${TOTAL} ($((100 * (TOTAL - ${#MISSING_BLADE[@]}) / TOTAL))%)"

# Count PHP defs (exclude the interface file)
PHP_COUNT=$(ls "$PHP_DIR"/*BlockDefinition.php 2>/dev/null | grep -cv '/BlockDefinition.php$' || echo 0)
echo -e "    PHP Defs:  ${PHP_COUNT}/${TOTAL} ($((100 * PHP_COUNT / TOTAL))%)"
echo ""

# ─── Missing PHP definitions list ───
if [ ${#MISSING_PHP[@]} -gt 0 ]; then
  echo -e "${YELLOW}Blocks missing PHP BlockDefinition (${#MISSING_PHP[@]}):${NC}"
  for b in "${MISSING_PHP[@]}"; do
    echo "  - $b"
  done
  echo ""
fi

# ─── Exit code ───
if [ $ERRORS -gt 0 ]; then
  echo -e "${RED}AUDIT FAILED — ${ERRORS} blocks have missing critical files${NC}"
  exit 1
elif [ $WARNINGS -gt 0 ]; then
  echo -e "${YELLOW}AUDIT PASSED WITH WARNINGS — ${WARNINGS} blocks lack PHP definitions${NC}"
  exit 0
else
  echo -e "${GREEN}AUDIT PASSED — all blocks are complete across all layers${NC}"
  exit 0
fi
