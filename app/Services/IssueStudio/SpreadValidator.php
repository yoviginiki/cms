<?php

namespace App\Services\IssueStudio;

/**
 * Hard validation of a generated spread document against the element
 * contract and the brief's material inventory. Errors feed the repair
 * round-trip verbatim.
 */
class SpreadValidator
{
    private const MAX_ELEMENTS_PER_PAGE = 24;

    /**
     * @return string[] errors (empty = valid)
     */
    public function validate(array $doc, bool $isCover, array $brief): array
    {
        $errors = [];
        $pages = $doc['pages'] ?? null;

        if (!is_array($pages) || $pages === []) {
            return ['No pages in the response.'];
        }

        if ($isCover) {
            if (count($pages) !== 1 || ($pages[0]['side'] ?? '') !== 'single') {
                $errors[] = 'A cover is exactly ONE page with side "single".';
            }
        } else {
            $sides = array_map(fn ($p) => $p['side'] ?? '', $pages);
            if (count($pages) !== 2 || $sides !== ['left', 'right']) {
                $errors[] = 'A spread is exactly TWO pages: side "left" then side "right".';
            }
        }

        if (trim((string) ($doc['editorial_note'] ?? '')) === '') {
            $errors[] = 'editorial_note is required (1-2 plain sentences for the user).';
        }

        $imageMaterials = [];
        foreach ($brief['materials'] ?? [] as $m) {
            if (($m['kind'] ?? '') === 'image' && !empty($m['asset_id'])) {
                $imageMaterials[] = $m['id'];
            }
        }

        foreach ($pages as $pi => $page) {
            $elements = $page['elements'] ?? [];
            if (!is_array($elements) || $elements === []) {
                $errors[] = "Page {$pi}: no elements.";
                continue;
            }
            if (count($elements) > self::MAX_ELEMENTS_PER_PAGE) {
                $errors[] = "Page {$pi}: too many elements (max " . self::MAX_ELEMENTS_PER_PAGE . ') — simplify; every spread has ONE dominant element.';
            }

            foreach ($elements as $ei => $el) {
                $label = "Page {$pi} element {$ei}";
                $type = (string) ($el['type'] ?? '');

                if (!isset(SpreadElementContract::TYPE_MAP[$type])) {
                    $errors[] = "{$label}: unknown element type \"{$type}\".";
                    continue;
                }

                $x = (float) ($el['x'] ?? -1);
                $y = (float) ($el['y'] ?? -1);
                $w = (float) ($el['w'] ?? 0);
                $h = (float) ($el['h'] ?? 0);

                // rules may be 1pt hairlines; everything else needs real area
                $minDim = in_array($type, ['line', 'decorative_rule'], true) ? 1 : 2;
                if ($w < $minDim || $h < $minDim) {
                    $errors[] = "{$label} ({$type}): too small — width and height must be >= {$minDim}pt.";
                }
                if ($x < 0 || $y < 0 || $x + $w > 595.5 || $y + $h > 842.5) {
                    $errors[] = "{$label} ({$type}): outside the page — x/y >= 0, x+w <= 595, y+h <= 842 (page-local points).";
                }

                if (in_array($type, SpreadElementContract::TEXT_TYPES, true)) {
                    if (trim(strip_tags((string) ($el['html'] ?? ''))) === '') {
                        $errors[] = "{$label} ({$type}): html text is empty.";
                    }
                    // hard character budget (chars ≈ area / (7 × fontSize)), 40% tolerance
                    $fontSize = (float) ($el['font_size'] ?? ($type === 'headline_frame' ? 54 : 10));
                    $budget = (int) max(60, ($w * $h) / (7 * max(8, $fontSize)));
                    $chars = mb_strlen(strip_tags((string) ($el['html'] ?? '')));
                    if ($chars > $budget * 1.4) {
                        $errors[] = "{$label} ({$type}): ~{$chars} characters won't fit a {$w}x{$h}pt frame (budget ≈ {$budget}). Cut the text editorially.";
                    }
                    if ($type !== 'headline_frame' && ($x < 35.5 || $x + $w > 559.5)) {
                        $errors[] = "{$label} ({$type}): text must stay inside the live area (x 36..559).";
                    }
                }

                if (in_array($type, SpreadElementContract::IMAGE_TYPES, true)) {
                    $mid = (string) ($el['material_id'] ?? '');
                    if (!in_array($mid, $imageMaterials, true)) {
                        $errors[] = "{$label} ({$type}): material_id \"{$mid}\" is not an image material in the inventory. Available: " . (implode(', ', $imageMaterials) ?: '(none — use type-led elements instead)');
                    }
                    if (trim((string) ($el['alt'] ?? '')) === '') {
                        $errors[] = "{$label} ({$type}): alt text is required.";
                    }
                }

                if (in_array($type, SpreadElementContract::SHAPE_TYPES, true)) {
                    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($el['fill_color'] ?? ''))) {
                        $errors[] = "{$label} ({$type}): fill_color must be a hex color.";
                    }
                }

                if ($type === 'table_frame') {
                    $headers = $el['table_headers'] ?? null;
                    $rows = $el['table_rows'] ?? null;
                    if (!is_array($headers) || $headers === [] || !is_array($rows) || $rows === []) {
                        $errors[] = "{$label}: table_frame needs table_headers and table_rows.";
                    }
                }
            }
        }

        return $errors;
    }
}
