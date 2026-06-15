#!/bin/bash
# ============================================================
# site-compare.sh — Visual + structural comparison of two websites
# Usage: ./scripts/site-compare.sh <reference_url> <cms_url> [output_file]
# Example: ./scripts/site-compare.sh https://cytechno.com https://sys.ensodo.eu/sites/cytechno/
# ============================================================

set -euo pipefail

REF_URL="${1:?Usage: site-compare.sh <reference_url> <cms_url> [output_file]}"
CMS_URL="${2:?Usage: site-compare.sh <reference_url> <cms_url> [output_file]}"
OUTPUT="${3:-/tmp/site-comparison-report.md}"

echo "📊 Site Comparison Tool"
echo "   Reference: $REF_URL"
echo "   CMS:       $CMS_URL"
echo ""

# Fetch both pages
REF_HTML=$(curl -sL "$REF_URL" 2>/dev/null)
CMS_HTML=$(curl -sL "$CMS_URL" 2>/dev/null)

if [ -z "$REF_HTML" ] || [ -z "$CMS_HTML" ]; then
  echo "ERROR: Failed to fetch one or both URLs"
  exit 1
fi

# ── Helper functions ──
count() { echo "$1" | grep -c "$2" 2>/dev/null || echo "0"; }
extract_css_values() { echo "$1" | grep -oP "$2" | sort -u; }

# ── Start report ──
cat > "$OUTPUT" << 'HEADER'
# Site Comparison Report

HEADER
echo "Generated: $(date)" >> "$OUTPUT"
echo "Reference: $REF_URL" >> "$OUTPUT"
echo "CMS: $CMS_URL" >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 1. PAGE SIZE ──
REF_SIZE=${#REF_HTML}
CMS_SIZE=${#CMS_HTML}
echo "## 1. Page Size" >> "$OUTPUT"
echo "| Metric | Reference | CMS | Status |" >> "$OUTPUT"
echo "|--------|-----------|-----|--------|" >> "$OUTPUT"
echo "| HTML size | ${REF_SIZE}b | ${CMS_SIZE}b | $([ $CMS_SIZE -lt $((REF_SIZE * 2)) ] && echo '✅' || echo '⚠️ CMS much larger') |" >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 2. STRUCTURE ──
echo "## 2. Structure" >> "$OUTPUT"
echo "| Element | Reference | CMS | Status |" >> "$OUTPUT"
echo "|---------|-----------|-----|--------|" >> "$OUTPUT"

for tag in header nav section article footer h1 h2 h3 img a button form; do
  REF_COUNT=$(echo "$REF_HTML" | grep -oc "<${tag}[ >]" || echo "0")
  CMS_COUNT=$(echo "$CMS_HTML" | grep -oc "<${tag}[ >]" || echo "0")
  STATUS="✅"
  [ "$REF_COUNT" != "$CMS_COUNT" ] && STATUS="⚠️ diff"
  echo "| \`<${tag}>\` | $REF_COUNT | $CMS_COUNT | $STATUS |" >> "$OUTPUT"
done
echo "" >> "$OUTPUT"

# ── 3. TYPOGRAPHY ──
echo "## 3. Typography" >> "$OUTPUT"
echo "### Reference fonts:" >> "$OUTPUT"
echo "$REF_HTML" | grep -oP "font-family:[^;'}\"]*" | sort -u | head -10 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### CMS fonts:" >> "$OUTPUT"
echo "$CMS_HTML" | grep -oP "font-family:[^;'}\"]*" | sort -u | head -10 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"

# Font sizes
echo "### Reference font sizes:" >> "$OUTPUT"
echo "$REF_HTML" | grep -oP 'font-size:\s*[^;}"]+' | sort -u | head -15 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### CMS font sizes:" >> "$OUTPUT"
echo "$CMS_HTML" | grep -oP 'font-size:\s*[^;}"]+' | sort -u | head -15 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 4. COLORS ──
echo "## 4. Colors" >> "$OUTPUT"
echo "### Reference colors (hex):" >> "$OUTPUT"
echo "$REF_HTML" | grep -oP '#[0-9a-fA-F]{3,8}' | sort | uniq -c | sort -rn | head -15 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### CMS colors (hex):" >> "$OUTPUT"
echo "$CMS_HTML" | grep -oP '#[0-9a-fA-F]{3,8}' | sort | uniq -c | sort -rn | head -15 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 5. SPACING & LAYOUT ──
echo "## 5. Spacing & Layout" >> "$OUTPUT"
echo "### Reference max-width values:" >> "$OUTPUT"
echo "$REF_HTML" | grep -oP 'max-width:\s*[^;}"]+' | sort -u | head -10 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### CMS max-width values:" >> "$OUTPUT"
echo "$CMS_HTML" | grep -oP 'max-width:\s*[^;}"]+' | sort -u | head -10 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"

echo "### Reference padding values:" >> "$OUTPUT"
echo "$REF_HTML" | grep -oP 'padding:\s*[^;}"]+' | sort -u | head -10 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### CMS padding values:" >> "$OUTPUT"
echo "$CMS_HTML" | grep -oP 'padding:\s*[^;}"]+' | sort -u | head -10 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 6. BORDER RADIUS ──
echo "## 6. Border Radius" >> "$OUTPUT"
echo "| Site | Values |" >> "$OUTPUT"
echo "|------|--------|" >> "$OUTPUT"
REF_RADIUS=$(echo "$REF_HTML" | grep -oP 'border-radius:\s*[^;}"]+' | sort -u | tr '\n' ', ')
CMS_RADIUS=$(echo "$CMS_HTML" | grep -oP 'border-radius:\s*[^;}"]+' | sort -u | tr '\n' ', ')
echo "| Reference | ${REF_RADIUS:-none} |" >> "$OUTPUT"
echo "| CMS | ${CMS_RADIUS:-none} |" >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 6b. NAVIGATION STYLING ──
echo "## 6b. Navigation Styling" >> "$OUTPUT"
echo "| Property | Reference | CMS | Match |" >> "$OUTPUT"
echo "|----------|-----------|-----|-------|" >> "$OUTPUT"

# Nav link font-size
REF_NAV_SIZE=$(echo "$REF_HTML" | grep -oP 'nav[^{]*a\{[^}]*font-size:\K[^;]+' | head -1)
CMS_NAV_SIZE=$(echo "$CMS_HTML" | grep -oP 'menu-top-link[^}]*font-size:\K[^;]+' | head -1)
echo "| Nav font-size | ${REF_NAV_SIZE:-?} | ${CMS_NAV_SIZE:-?} | $([ "$REF_NAV_SIZE" = "$CMS_NAV_SIZE" ] && echo '✅' || echo '⚠️') |" >> "$OUTPUT"

# Nav link font-weight
REF_NAV_WEIGHT=$(echo "$REF_HTML" | grep -oP 'nav[^{]*a\{[^}]*font-weight:\K[^;]+' | head -1)
CMS_NAV_WEIGHT=$(echo "$CMS_HTML" | grep -oP 'menu-top-link[^}]*font-weight:\K[^;]+' | head -1)
echo "| Nav font-weight | ${REF_NAV_WEIGHT:-?} | ${CMS_NAV_WEIGHT:-?} | $([ "$REF_NAV_WEIGHT" = "$CMS_NAV_WEIGHT" ] && echo '✅' || echo '⚠️') |" >> "$OUTPUT"

# Nav link letter-spacing
REF_NAV_TRACK=$(echo "$REF_HTML" | grep -oP 'nav[^{]*a\{[^}]*letter-spacing:\K[^;]+' | head -1)
CMS_NAV_TRACK=$(echo "$CMS_HTML" | grep -oP 'menu-top-link[^}]*letter-spacing:\K[^;]+' | head -1)
echo "| Nav letter-spacing | ${REF_NAV_TRACK:-?} | ${CMS_NAV_TRACK:-?} | $([ "$REF_NAV_TRACK" = "$CMS_NAV_TRACK" ] && echo '✅' || echo '⚠️') |" >> "$OUTPUT"

# Nav gap
REF_NAV_GAP=$(echo "$REF_HTML" | grep -oP 'nav-links\{[^}]*gap:\K[^;]+' | head -1)
CMS_NAV_GAP=$(echo "$CMS_HTML" | grep -oP 'menu-desktop[^}]*gap:\K[^;]+' | head -1)
echo "| Nav gap | ${REF_NAV_GAP:-?} | ${CMS_NAV_GAP:-?} | $([ "$REF_NAV_GAP" = "$CMS_NAV_GAP" ] && echo '✅' || echo '⚠️') |" >> "$OUTPUT"

# Nav CTA (border on last link)
REF_HAS_CTA=$(echo "$REF_HTML" | grep -c 'nav-cta' 2>/dev/null || echo "0")
CMS_HAS_CTA=$(echo "$CMS_HTML" | grep -c 'border.*solid.*red\|border.*solid.*primary' 2>/dev/null || echo "0")
echo "| Nav CTA button | ${REF_HAS_CTA} | ${CMS_HAS_CTA} | $([ "$REF_HAS_CTA" = "$CMS_HAS_CTA" ] && echo '✅' || echo '⚠️') |" >> "$OUTPUT"

# Nav link border (should be none on regular links)
REF_LINK_BORDER=$(echo "$REF_HTML" | grep -oP 'nav-links a\{[^}]*border[^}]*' | head -1)
CMS_LINK_BORDER=$(echo "$CMS_HTML" | grep -oP 'menu-top-link[^}]*border[^}]*' | head -1)
echo "| Regular links border | ${REF_LINK_BORDER:-none} | ${CMS_LINK_BORDER:-none} | $([ -z "$REF_LINK_BORDER" ] && [ -z "$CMS_LINK_BORDER" ] && echo '✅' || echo '⚠️') |" >> "$OUTPUT"

echo "" >> "$OUTPUT"

# ── 7. SHADOWS ──
echo "## 7. Shadows" >> "$OUTPUT"
REF_SHADOWS=$(count "$REF_HTML" "box-shadow")
CMS_SHADOWS=$(count "$CMS_HTML" "box-shadow")
echo "| Site | box-shadow count |" >> "$OUTPUT"
echo "|------|-----------------|" >> "$OUTPUT"
echo "| Reference | $REF_SHADOWS |" >> "$OUTPUT"
echo "| CMS | $CMS_SHADOWS |" >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 8. CSS VARIABLES ──
echo "## 8. CSS Variables Used" >> "$OUTPUT"
echo "### Reference:" >> "$OUTPUT"
echo "$REF_HTML" | grep -oP 'var\(--[a-zA-Z0-9_-]+' | sort -u | head -20 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### CMS:" >> "$OUTPUT"
echo "$CMS_HTML" | grep -oP 'var\(--[a-zA-Z0-9_-]+' | sort -u | head -30 | sed 's/^/- /' >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 9. IMAGES ──
echo "## 9. Images" >> "$OUTPUT"
REF_IMGS=$(count "$REF_HTML" "<img")
CMS_IMGS=$(count "$CMS_HTML" "<img")
echo "| Site | \`<img>\` count |" >> "$OUTPUT"
echo "|------|--------------|" >> "$OUTPUT"
echo "| Reference | $REF_IMGS |" >> "$OUTPUT"
echo "| CMS | $CMS_IMGS |" >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 10. INTERACTIVE ELEMENTS ──
echo "## 10. Interactive Elements" >> "$OUTPUT"
echo "| Element | Reference | CMS |" >> "$OUTPUT"
echo "|---------|-----------|-----|" >> "$OUTPUT"
echo "| Links | $(count "$REF_HTML" '<a ') | $(count "$CMS_HTML" '<a ') |" >> "$OUTPUT"
echo "| Buttons | $(count "$REF_HTML" '<button') | $(count "$CMS_HTML" '<button') |" >> "$OUTPUT"
echo "| Forms | $(count "$REF_HTML" '<form') | $(count "$CMS_HTML" '<form') |" >> "$OUTPUT"
echo "| Inputs | $(count "$REF_HTML" '<input') | $(count "$CMS_HTML" '<input') |" >> "$OUTPUT"
echo "" >> "$OUTPUT"

# ── 11. HARDCODED VALUES IN CMS (potential issues) ──
echo "## 11. CMS Hardcoded Values (should be CSS variables)" >> "$OUTPUT"
echo "| Pattern | Count | Recommendation |" >> "$OUTPUT"
echo "|---------|-------|---------------|" >> "$OUTPUT"
for pattern in "opacity:0.8" "opacity:0.6" "opacity:0.7" "border-radius:12px" "border-radius:0.75rem" "border-radius:8px" "height:56px" "font-size:0.875rem" "font-size:18px" "font-size:13px" "gap:24px" "#3b82f6" "#e5e7eb" "#6b7280" "#64748b" "#1e293b" "Georgia.*serif"; do
  C=$(echo "$CMS_HTML" | grep -c "$pattern" 2>/dev/null || echo "0")
  if [ "$C" -gt 0 ]; then
    echo "| \`$pattern\` | $C | Replace with CSS variable |" >> "$OUTPUT"
  fi
done
echo "" >> "$OUTPUT"

# ── 12. MISSING IN CMS ──
echo "## 12. Elements in Reference but Missing in CMS" >> "$OUTPUT"

# Check for specific classes/elements in reference that CMS doesn't have
for class in "fade-up" "founder-card" "stats-grid" "service-row" "portfolio-grid" "p-card" "nav-cta" "hero-fade" "footer-inner" "footer-links"; do
  REF_HAS=$(echo "$REF_HTML" | grep -c "$class" 2>/dev/null || echo "0")
  CMS_HAS=$(echo "$CMS_HTML" | grep -c "$class" 2>/dev/null || echo "0")
  if [ "$REF_HAS" -gt 0 ] && [ "$CMS_HAS" -eq 0 ]; then
    echo "- \`.$class\` — present in reference ($REF_HAS), missing in CMS" >> "$OUTPUT"
  fi
done
echo "" >> "$OUTPUT"

# ── 13. ACTION PLAN ──
echo "## 13. Recommended Action Plan" >> "$OUTPUT"
echo "" >> "$OUTPUT"
echo "### Priority 1: Theme Token Fixes" >> "$OUTPUT"
echo "CSS variables that need to be set in the theme:" >> "$OUTPUT"
echo "" >> "$OUTPUT"

echo "### Priority 2: Block Template Fixes" >> "$OUTPUT"
echo "Blade templates that need to use CSS variables instead of hardcoded values:" >> "$OUTPUT"
echo "" >> "$OUTPUT"

echo "### Priority 3: Layout/Nav Fixes" >> "$OUTPUT"
echo "Layout and navigation changes needed:" >> "$OUTPUT"
echo "" >> "$OUTPUT"

echo "### Priority 4: New Features Needed" >> "$OUTPUT"
echo "CMS features that don't exist yet but are needed:" >> "$OUTPUT"
echo "" >> "$OUTPUT"

echo "---" >> "$OUTPUT"
echo "*Generated by site-compare.sh — $(date)*" >> "$OUTPUT"

echo ""
echo "✅ Report generated: $OUTPUT"
echo ""
echo "=== QUICK SUMMARY ==="
echo "Page size: REF=${REF_SIZE}b vs CMS=${CMS_SIZE}b"
echo "Sections: REF=$(count "$REF_HTML" '<section') vs CMS=$(count "$CMS_HTML" '<section')"
echo "Images: REF=$REF_IMGS vs CMS=$CMS_IMGS"
echo "Links: REF=$(count "$REF_HTML" '<a ') vs CMS=$(count "$CMS_HTML" '<a ')"
echo "Shadows: REF=$REF_SHADOWS vs CMS=$CMS_SHADOWS"

# Count differences
DIFFS=0
for tag in header nav section article footer h1 h2 h3 img; do
  R=$(echo "$REF_HTML" | grep -oc "<${tag}[ >]" || echo "0")
  C=$(echo "$CMS_HTML" | grep -oc "<${tag}[ >]" || echo "0")
  [ "$R" != "$C" ] && DIFFS=$((DIFFS + 1))
done
echo "Structural differences: $DIFFS elements differ"
echo ""
echo "Full report: cat $OUTPUT"
