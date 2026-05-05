<?php

namespace App\Services\Magazine;

/**
 * Editorial Magazine Layout Engine
 *
 * Thinks in SPREADS (2-page pairs), not individual pages.
 * Designs rhythm: dense → pause → visual → intimate → crescendo → silence.
 * Every spread has a PURPOSE, a DENSITY, and an EMOTIONAL TONE.
 *
 * Hard rules:
 * - Never stack content linearly (no "book mode")
 * - Never repeat the same layout type consecutively
 * - At least 30% of spreads are highly visual
 * - At least 20% are minimal (almost empty)
 * - At least 20% break the grid or are asymmetric
 * - White space is a design element, not wasted space
 */
class LayoutEngine
{
    private TemplateRegistry $templates;

    // A4 in points
    private const PW = 595;
    private const PH = 842;
    private const ML = 48;
    private const MR = 48;
    private const MT = 60;
    private const MB = 60;
    private const CW = 499; // PW - ML - MR
    private const PLACEHOLDER = '#e8e4df';

    public function __construct(TemplateRegistry $templates)
    {
        $this->templates = $templates;
    }

    /**
     * Main entry point. Returns array of page specs (raw elements, not template IDs).
     * Each page spec = ['page_number' => N, 'template_id' => '...', 'slots' => [...]]
     */
    public function compose(array $brief, array $items, array $curation): array
    {
        $analysis = $this->analyzeContent($items);
        $spreads = $this->planSpreads($brief, $analysis, $items, $curation);
        $pages = $this->spreadsToPages($spreads, $items, $brief, $analysis);

        // Number pages
        foreach ($pages as $i => &$p) {
            $p['page_number'] = $i + 1;
        }

        return $pages;
    }

    // ═══════════════════════════════════════════════════════════
    // PHASE 1 — CONTENT ANALYSIS
    // ═══════════════════════════════════════════════════════════

    private function analyzeContent(array $items): array
    {
        $analysis = [
            'total_items' => count($items),
            'total_words' => 0,
            'has_images' => 0,
            'long_articles' => [],
            'medium_articles' => [],
            'short_pieces' => [],
            'pullquotes' => [],
            'items_by_weight' => [],
        ];

        foreach ($items as $item) {
            $body = $item['body'] ?? $item['text'] ?? '';
            $wordCount = str_word_count(strip_tags($body));
            $hasImage = !empty($item['featured_image']);
            $title = $item['title'] ?? '';
            $id = $item['id'];

            $analysis['total_words'] += $wordCount;
            if ($hasImage) $analysis['has_images']++;

            if ($wordCount > 800) $analysis['long_articles'][] = $id;
            elseif ($wordCount > 300) $analysis['medium_articles'][] = $id;
            else $analysis['short_pieces'][] = $id;

            // Extract pullquote
            $pq = $this->extractPullquote($body);
            if ($pq) $analysis['pullquotes'][$id] = $pq;

            // Weight: length + image + title importance
            $weight = $wordCount / 100;
            if ($hasImage) $weight += 3;
            if (mb_strlen($title) > 40) $weight += 1;
            $analysis['items_by_weight'][$id] = $weight;
        }

        arsort($analysis['items_by_weight']);
        return $analysis;
    }

    // ═══════════════════════════════════════════════════════════
    // PHASE 2 — SPREAD PLANNING (Editorial Rhythm)
    // ═══════════════════════════════════════════════════════════

    /**
     * Plan the magazine as a sequence of SPREADS.
     * Each spread has: type, purpose, density, tone, item_ids, section.
     */
    private function planSpreads(array $brief, array $analysis, array $items, array $curation): array
    {
        $spreads = [];
        $sections = $curation['sections'] ?? [];
        $keptIds = collect($curation['decisions'] ?? [])
            ->where('decision', 'kept')
            ->pluck('item_id')
            ->toArray();

        $itemsById = [];
        foreach ($items as $item) {
            $itemsById[$item['id']] = $item;
        }

        // Rank items by weight
        $ranked = array_keys($analysis['items_by_weight']);
        $ranked = array_intersect($ranked, $keptIds);
        $ranked = array_values($ranked);

        $heroItem = $ranked[0] ?? null;
        $featureItems = array_slice($ranked, 1, 2); // next 2 strongest
        $remaining = array_slice($ranked, 3);

        // ─── S1: COVER + INSIDE COVER ───
        $spreads[] = [
            'type' => 'cover_spread',
            'purpose' => 'intro',
            'density' => 'minimal',
            'tone' => 'intrigue',
            'item_ids' => [],
        ];

        // ─── S2: TOC + EDITOR'S NOTE ───
        $spreads[] = [
            'type' => 'toc_editorial',
            'purpose' => 'orientation',
            'density' => 'medium',
            'tone' => 'calm',
            'item_ids' => [],
            'sections' => $sections,
        ];

        // ─── S3: SECTION OPENER + FEATURE LEAD ───
        if ($heroItem) {
            $spreads[] = [
                'type' => 'section_opener_feature_lead',
                'purpose' => 'transition',
                'density' => 'minimal',
                'tone' => 'anticipation',
                'item_ids' => [$heroItem],
                'section' => $sections[0] ?? null,
            ];
        }

        // ─── S4: FEATURE ARTICLE (dense spread) ───
        if ($heroItem) {
            $spreads[] = [
                'type' => 'feature_dense_spread',
                'purpose' => 'dense_reading',
                'density' => 'dense',
                'tone' => 'immersive',
                'item_ids' => [$heroItem],
            ];
        }

        // ─── S5: VISUAL PAUSE + PULLQUOTE ───
        $pauseQuote = '';
        if ($heroItem && isset($analysis['pullquotes'][$heroItem])) {
            $pauseQuote = $analysis['pullquotes'][$heroItem];
        }
        $spreads[] = [
            'type' => 'visual_pause_pullquote',
            'purpose' => 'visual_pause',
            'density' => 'minimal',
            'tone' => 'contemplative',
            'item_ids' => [],
            'pullquote' => $pauseQuote,
            'attribution' => $heroItem ? ($itemsById[$heroItem]['title'] ?? '') : '',
        ];

        // ─── S6+: CONTENT SPREADS (alternate patterns) ───
        $spreadPatterns = [
            'image_led_article',       // S6: dominant image left, text right
            'photo_essay_fullbleed',   // S7: silence, pure visual
            'long_read_three_col',     // S8: dense 3-column reading
            'interlude_fragmented',    // S9: deconstructed, poetic
            'asymmetric_article',      // S10: diagonal energy
        ];

        $contentItems = array_merge($featureItems, $remaining);
        $patternIndex = 0;

        foreach ($contentItems as $idx => $itemId) {
            $pattern = $spreadPatterns[$patternIndex % count($spreadPatterns)];
            $patternIndex++;

            $spreads[] = [
                'type' => $pattern,
                'purpose' => $this->patternPurpose($pattern),
                'density' => $this->patternDensity($pattern),
                'tone' => $this->patternTone($pattern),
                'item_ids' => [$itemId],
            ];

            // After every 2 content spreads that aren't already minimal, insert a breath
            if ($patternIndex % 3 === 0 && $pattern !== 'photo_essay_fullbleed' && $pattern !== 'interlude_fragmented') {
                $pqId = $contentItems[$idx] ?? null;
                $spreads[] = [
                    'type' => 'visual_pause_pullquote',
                    'purpose' => 'visual_pause',
                    'density' => 'minimal',
                    'tone' => 'contemplative',
                    'item_ids' => [],
                    'pullquote' => $pqId && isset($analysis['pullquotes'][$pqId]) ? $analysis['pullquotes'][$pqId] : '',
                    'attribution' => $pqId ? ($itemsById[$pqId]['title'] ?? '') : '',
                ];
            }
        }

        // ─── S11: VISUAL CRESCENDO ───
        $lastContentItem = end($contentItems) ?: $heroItem;
        $spreads[] = [
            'type' => 'visual_crescendo',
            'purpose' => 'emotional_peak',
            'density' => 'minimal',
            'tone' => 'emotional',
            'item_ids' => $lastContentItem ? [$lastContentItem] : [],
            'pullquote' => $lastContentItem && isset($analysis['pullquotes'][$lastContentItem])
                ? $analysis['pullquotes'][$lastContentItem] : ($brief['theme'] ?? ''),
        ];

        // ─── S12: CLOSING + COLOPHON ───
        $spreads[] = [
            'type' => 'closing_colophon',
            'purpose' => 'resolution',
            'density' => 'minimal',
            'tone' => 'quiet',
            'item_ids' => [],
        ];

        return $spreads;
    }

    private function patternPurpose(string $pattern): string
    {
        return match ($pattern) {
            'image_led_article' => 'visual_reading',
            'photo_essay_fullbleed' => 'visual_pause',
            'long_read_three_col' => 'dense_reading',
            'interlude_fragmented' => 'transition',
            'asymmetric_article' => 'dynamic_reading',
            default => 'content',
        };
    }

    private function patternDensity(string $pattern): string
    {
        return match ($pattern) {
            'image_led_article' => 'medium',
            'photo_essay_fullbleed' => 'minimal',
            'long_read_three_col' => 'dense',
            'interlude_fragmented' => 'minimal',
            'asymmetric_article' => 'medium',
            default => 'medium',
        };
    }

    private function patternTone(string $pattern): string
    {
        return match ($pattern) {
            'image_led_article' => 'engaging',
            'photo_essay_fullbleed' => 'silence',
            'long_read_three_col' => 'intellectual',
            'interlude_fragmented' => 'poetic',
            'asymmetric_article' => 'dynamic',
            default => 'informative',
        };
    }

    // ═══════════════════════════════════════════════════════════
    // PHASE 3 — SPREAD → PAGE CONVERSION
    // ═══════════════════════════════════════════════════════════

    /**
     * Convert each spread into 2 page specs.
     * Each page spec uses template_id + slots for the IssueRendererService.
     */
    private function spreadsToPages(array $spreads, array $items, array $brief, array $analysis): array
    {
        $pages = [];
        $itemsById = [];
        foreach ($items as $item) {
            $itemsById[$item['id']] = $item;
        }

        foreach ($spreads as $spread) {
            $type = $spread['type'];
            $item = null;
            if (!empty($spread['item_ids'])) {
                $item = $itemsById[$spread['item_ids'][0]] ?? null;
            }

            $spreadPages = match ($type) {
                'cover_spread' => $this->renderCoverSpread($brief),
                'toc_editorial' => $this->renderTocEditorial($brief, $spread['sections'] ?? [], $items),
                'section_opener_feature_lead' => $this->renderSectionOpenerFeatureLead($item, $spread['section'] ?? null),
                'feature_dense_spread' => $this->renderFeatureDenseSpread($item, $analysis),
                'visual_pause_pullquote' => $this->renderVisualPausePullquote($spread),
                'image_led_article' => $this->renderImageLedArticle($item),
                'photo_essay_fullbleed' => $this->renderPhotoEssayFullbleed($item),
                'long_read_three_col' => $this->renderLongReadThreeCol($item, $analysis),
                'interlude_fragmented' => $this->renderInterludeFragmented($item),
                'asymmetric_article' => $this->renderAsymmetricArticle($item, $analysis),
                'visual_crescendo' => $this->renderVisualCrescendo($spread),
                'closing_colophon' => $this->renderClosingColophon($brief),
                default => $this->renderGenericSpread($item),
            };

            foreach ($spreadPages as $p) {
                $pages[] = $p;
            }
        }

        return $pages;
    }

    // ═══════════════════════════════════════════════════════════
    // SPREAD RENDERERS — Each returns exactly 2 pages
    // ═══════════════════════════════════════════════════════════

    /**
     * S1: Cover spread
     * Left: Full-bleed image (or placeholder). Right: Title in vast whitespace.
     */
    private function renderCoverSpread(array $brief): array
    {
        $title = $brief['title'] ?? 'Untitled';
        $subtitle = $brief['subtitle'] ?? $brief['theme'] ?? '';

        return [
            // Page 1 (left): Full-bleed image
            [
                'section_id' => 'cover',
                'template_id' => 'ed_cover_image',
                'density' => 'visual',
                'slots' => ['image_1' => ''],
            ],
            // Page 2 (right): Title in white space
            [
                'section_id' => 'cover',
                'template_id' => 'ed_cover_title',
                'density' => 'minimal',
                'slots' => [
                    'title' => $title,
                    'subtitle' => $subtitle,
                ],
            ],
        ];
    }

    /**
     * S2: TOC + Editor's note
     * Left: Narrow TOC strip. Right: Editor's note in narrow column.
     */
    private function renderTocEditorial(array $brief, array $sections, array $items): array
    {
        // Build TOC text
        $tocLines = [];
        $pageNum = 5; // TOC starts referencing page 5
        foreach ($sections as $sec) {
            $tocLines[] = str_pad((string)$pageNum, 3, ' ', STR_PAD_LEFT) . '  ' . ($sec['title'] ?? 'Section');
            $pageNum += 4;
        }
        $tocText = implode("\n", $tocLines) ?: "5  Opening\n9  Features\n15  Gallery\n19  Reflection";

        $editorNote = $brief['intention'] ?? $brief['theme'] ?? '';
        if (!$editorNote) {
            $editorNote = 'A collection of perspectives on ' . ($brief['title'] ?? 'our theme') . '.';
        }

        return [
            // Page 1 (left): TOC
            [
                'section_id' => 'toc',
                'template_id' => 'ed_toc',
                'density' => 'medium',
                'slots' => [
                    'title' => 'Contents',
                    'body' => $tocText,
                    'image_1' => '', // small thumbnail
                ],
            ],
            // Page 2 (right): Editor's note
            [
                'section_id' => 'toc',
                'template_id' => 'ed_editors_note',
                'density' => 'medium',
                'slots' => [
                    'title' => 'From the editor',
                    'body' => $editorNote,
                ],
            ],
        ];
    }

    /**
     * S3: Section opener (left nearly empty) + Feature lead (right: full-bleed + headline)
     */
    private function renderSectionOpenerFeatureLead(?array $item, ?array $section): array
    {
        $sectionTitle = $section['title'] ?? 'I';
        $sectionDesc = $section['one_line_description'] ?? '';
        $itemTitle = $item['title'] ?? '';
        $image = $item['featured_image'] ?? '';

        return [
            // Page 1 (left): Section number + title, 90% empty
            [
                'section_id' => 'section',
                'template_id' => 'ed_section_opener_minimal',
                'density' => 'minimal',
                'slots' => [
                    'title' => $sectionTitle,
                    'subtitle' => $sectionDesc,
                ],
            ],
            // Page 2 (right): Full-bleed image with headline overlay at bottom
            [
                'section_id' => 'section',
                'template_id' => 'ed_feature_lead_fullbleed',
                'density' => 'visual',
                'slots' => [
                    'title' => $itemTitle,
                    'image_1' => $image,
                ],
            ],
        ];
    }

    /**
     * S4: Feature article — broken grid, drop cap, inset image
     */
    private function renderFeatureDenseSpread(?array $item, array $analysis): array
    {
        if (!$item) return $this->renderEmptySpread();

        $title = $item['title'] ?? '';
        $body = $item['body'] ?? $item['text'] ?? '';
        $image = $item['featured_image'] ?? '';
        $wordCount = str_word_count(strip_tags($body));

        // Split body for two pages
        $mid = $this->splitAtWord($body, min((int)(strlen($body) * 0.5), 3000));
        $bodyLeft = mb_substr($body, 0, $mid);
        $bodyRight = mb_substr($body, $mid);

        return [
            // Page 1 (left): Two unequal columns, drop cap
            [
                'section_id' => 'feature',
                'template_id' => 'ed_feature_broken_grid_left',
                'density' => 'dense',
                'slots' => [
                    'title' => $title,
                    'body' => $bodyLeft ?: mb_substr($body, 0, 2500),
                ],
            ],
            // Page 2 (right): Single wide column + inset image top-right
            [
                'section_id' => 'feature',
                'template_id' => 'ed_feature_broken_grid_right',
                'density' => 'dense',
                'slots' => [
                    'body' => $bodyRight ?: mb_substr($body, 2500, 2500),
                    'image_1' => $image,
                    'caption_1' => $title,
                ],
            ],
        ];
    }

    /**
     * S5 / breathing: Visual pause — left page empty with thin rule, right page: centered pullquote
     */
    private function renderVisualPausePullquote(array $spread): array
    {
        $pullquote = $spread['pullquote'] ?? '';
        $attribution = $spread['attribution'] ?? '';

        return [
            // Page 1 (left): Almost completely empty — thin rule only
            [
                'section_id' => 'rhythm',
                'template_id' => 'ed_silence_rule',
                'density' => 'minimal',
                'slots' => [],
            ],
            // Page 2 (right): Centered pullquote
            [
                'section_id' => 'rhythm',
                'template_id' => 'ed_pullquote_centered',
                'density' => 'minimal',
                'slots' => [
                    'pullquote' => $pullquote,
                    'attribution' => $attribution,
                ],
            ],
        ];
    }

    /**
     * S6: Image-led article — dominant image left, text right
     */
    private function renderImageLedArticle(?array $item): array
    {
        if (!$item) return $this->renderEmptySpread();

        $title = $item['title'] ?? '';
        $body = $item['body'] ?? $item['text'] ?? '';
        $image = $item['featured_image'] ?? '';

        return [
            // Page 1 (left): Full-page image, bleeding off left/top/bottom
            [
                'section_id' => 'content',
                'template_id' => 'ed_fullpage_image',
                'density' => 'visual',
                'slots' => ['image_1' => $image],
            ],
            // Page 2 (right): Text in single column shifted right (30% left margin)
            [
                'section_id' => 'content',
                'template_id' => 'ed_text_right_aligned',
                'density' => 'medium',
                'slots' => [
                    'title' => $title,
                    'body' => mb_substr($body, 0, 2200),
                ],
            ],
        ];
    }

    /**
     * S7: Photo essay — both pages full-bleed, tiny caption
     */
    private function renderPhotoEssayFullbleed(?array $item): array
    {
        $image = $item['featured_image'] ?? '';
        $title = $item['title'] ?? '';

        return [
            // Page 1 (left): Full-bleed image
            [
                'section_id' => 'visual',
                'template_id' => 'ed_fullbleed_image',
                'density' => 'minimal',
                'slots' => ['image_1' => $image],
            ],
            // Page 2 (right): Full-bleed image (or continuation) with tiny caption
            [
                'section_id' => 'visual',
                'template_id' => 'ed_fullbleed_image_caption',
                'density' => 'minimal',
                'slots' => [
                    'image_1' => $image,
                    'caption_1' => $title,
                ],
            ],
        ];
    }

    /**
     * S8: Long read — three-column grid, dense, with marginalia
     */
    private function renderLongReadThreeCol(?array $item, array $analysis): array
    {
        if (!$item) return $this->renderEmptySpread();

        $title = $item['title'] ?? '';
        $body = $item['body'] ?? $item['text'] ?? '';
        $image = $item['featured_image'] ?? '';
        $pullquote = $analysis['pullquotes'][$item['id']] ?? '';

        $mid = $this->splitAtWord($body, min((int)(strlen($body) * 0.5), 3000));

        return [
            // Page 1 (left): Three columns, image spanning cols 2-3 at top
            [
                'section_id' => 'content',
                'template_id' => 'ed_three_column_image',
                'density' => 'dense',
                'slots' => [
                    'title' => $title,
                    'body' => mb_substr($body, 0, $mid) ?: mb_substr($body, 0, 2500),
                    'image_1' => $image,
                ],
            ],
            // Page 2 (right): Cols 1-2 text, col 3 marginalia/pullquote
            [
                'section_id' => 'content',
                'template_id' => 'ed_three_column_marginalia',
                'density' => 'dense',
                'slots' => [
                    'body' => mb_substr($body, $mid) ?: mb_substr($body, 2500, 2500),
                    'pullquote' => $pullquote,
                ],
            ],
        ];
    }

    /**
     * S9: Interlude — fragmented text, deconstructed, concrete poem feeling
     */
    private function renderInterludeFragmented(?array $item): array
    {
        $title = $item['title'] ?? '';
        $body = $item['body'] ?? $item['text'] ?? '';

        // Extract 3-4 short fragments from the content
        $fragments = $this->extractFragments($body, 4);

        return [
            // Page 1 (left): Abstract circle image + scattered fragment
            [
                'section_id' => 'interlude',
                'template_id' => 'ed_interlude_left',
                'density' => 'minimal',
                'slots' => [
                    'title' => $fragments[0] ?? $title,
                    'subtitle' => $fragments[1] ?? '',
                    'image_1' => $item['featured_image'] ?? '',
                ],
            ],
            // Page 2 (right): More fragments at irregular positions
            [
                'section_id' => 'interlude',
                'template_id' => 'ed_interlude_right',
                'density' => 'minimal',
                'slots' => [
                    'title' => $fragments[2] ?? $title,
                    'subtitle' => $fragments[3] ?? '',
                    'body' => mb_substr(strip_tags($body), 0, 300),
                ],
            ],
        ];
    }

    /**
     * S10: Asymmetric — diagonal energy, image wrapping, margin pullquote
     */
    private function renderAsymmetricArticle(?array $item, array $analysis): array
    {
        if (!$item) return $this->renderEmptySpread();

        $title = $item['title'] ?? '';
        $body = $item['body'] ?? $item['text'] ?? '';
        $image = $item['featured_image'] ?? '';
        $pullquote = $analysis['pullquotes'][$item['id']] ?? '';

        $mid = $this->splitAtWord($body, min((int)(strlen($body) * 0.5), 2500));

        return [
            // Page 1 (left): Image 60% top-left, text wraps bottom-right
            [
                'section_id' => 'content',
                'template_id' => 'ed_asymmetric_image_text',
                'density' => 'medium',
                'slots' => [
                    'title' => $title,
                    'body' => mb_substr($body, 0, $mid) ?: mb_substr($body, 0, 1800),
                    'image_1' => $image,
                ],
            ],
            // Page 2 (right): Two columns with pullquote breaking into gutter
            [
                'section_id' => 'content',
                'template_id' => 'ed_two_col_margin_quote',
                'density' => 'medium',
                'slots' => [
                    'body' => mb_substr($body, $mid) ?: mb_substr($body, 1800, 2000),
                    'pullquote' => $pullquote,
                ],
            ],
        ];
    }

    /**
     * S11: Visual crescendo — full-bleed moody image left, single quote right
     */
    private function renderVisualCrescendo(array $spread): array
    {
        $pullquote = $spread['pullquote'] ?? '';

        return [
            // Page 1 (left): Full-bleed atmospheric image
            [
                'section_id' => 'crescendo',
                'template_id' => 'ed_fullbleed_image',
                'density' => 'minimal',
                'slots' => ['image_1' => ''],
            ],
            // Page 2 (right): Single large quote, thin rules, extreme whitespace
            [
                'section_id' => 'crescendo',
                'template_id' => 'ed_grand_pullquote',
                'density' => 'minimal',
                'slots' => [
                    'pullquote' => $pullquote,
                ],
            ],
        ];
    }

    /**
     * S12: Closing — fading text left, colophon right
     */
    private function renderClosingColophon(array $brief): array
    {
        $title = $brief['title'] ?? '';
        $theme = $brief['theme'] ?? $brief['intention'] ?? '';

        return [
            // Page 1 (left): Closing thought centered in bottom third
            [
                'section_id' => 'closing',
                'template_id' => 'ed_closing_thought',
                'density' => 'minimal',
                'slots' => [
                    'pullquote' => $theme,
                    'title' => $title,
                ],
            ],
            // Page 2 (right): Colophon at very bottom, rest empty
            [
                'section_id' => 'closing',
                'template_id' => 'ed_colophon',
                'density' => 'minimal',
                'slots' => [
                    'title' => $title,
                    'body' => 'Published with Ensodo',
                ],
            ],
        ];
    }

    private function renderEmptySpread(): array
    {
        return [
            ['section_id' => 'filler', 'template_id' => 'ed_silence_rule', 'density' => 'minimal', 'slots' => []],
            ['section_id' => 'filler', 'template_id' => 'ed_silence_rule', 'density' => 'minimal', 'slots' => []],
        ];
    }

    private function renderGenericSpread(?array $item): array
    {
        if (!$item) return $this->renderEmptySpread();
        return $this->renderImageLedArticle($item);
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function extractPullquote(string $body): string
    {
        if (!$body) return '';
        $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($body), 8);
        foreach ($sentences as $s) {
            $s = trim($s);
            if (mb_strlen($s) > 30 && mb_strlen($s) < 180) {
                return $s;
            }
        }
        return '';
    }

    private function extractFragments(string $body, int $count): array
    {
        if (!$body) return [];
        $stripped = strip_tags($body);
        $sentences = preg_split('/(?<=[.!?])\s+/', $stripped, $count * 2);
        $fragments = [];
        foreach ($sentences as $s) {
            $s = trim($s);
            if (mb_strlen($s) > 10 && mb_strlen($s) < 100) {
                $fragments[] = $s;
                if (count($fragments) >= $count) break;
            }
        }
        return $fragments;
    }

    private function splitAtWord(string $text, int $near): int
    {
        $len = strlen($text);
        if ($len <= $near) return $len;
        $pos = $near;
        while ($pos > 0 && $text[$pos] !== ' ' && $text[$pos] !== "\n") $pos--;
        return $pos ?: $near;
    }
}
