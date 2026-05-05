<?php

namespace App\Services\Magazine;

class TemplateRegistry
{
    private array $templates;

    // A4 in points at 72dpi
    private const PW = 595;
    private const PH = 842;
    private const ML = 48;
    private const MR = 48;
    private const MT = 60;
    private const MB = 60;
    private const CW = 499; // PW - ML - MR
    private const PLACEHOLDER = '#e8e4df';

    public function __construct()
    {
        $this->templates = config('magazine_templates', []);
    }

    public function all(): array { return $this->templates; }
    public function get(string $id): ?array { return $this->templates[$id] ?? null; }

    public function toolAllowlist(): array
    {
        $list = [];
        foreach ($this->templates as $id => $def) {
            $list[] = [
                'id' => $id, 'label' => $def['label'], 'density' => $def['density'],
                'slots' => array_map(fn($s, $k) => ['name' => $k, 'type' => $s['type'], 'required' => $s['required'] ?? false], $def['slots'], array_keys($def['slots'])),
            ];
        }
        return $list;
    }

    public function byDensity(string $density): array
    {
        return array_filter($this->templates, fn($t) => $t['density'] === $density);
    }

    /**
     * Render a template into positioned magazine elements.
     *
     * Design vocabulary:
     *   - Generous whitespace (48pt margins, more for editorial pages)
     *   - Instrument Serif for headlines, Inter for body
     *   - Warm grey (#e8e4df) placeholder for missing images
     *   - Thin accent rules (1pt, #ddd or lighter)
     *   - Asymmetry > symmetry
     *   - Every page has at most 2-3 visual zones
     */
    public function render(string $templateId, array $slots, array $tokens = []): array
    {
        $blocks = [];
        $pw = self::PW; $ph = self::PH;
        $ml = self::ML; $mr = self::MR; $mt = self::MT; $mb = self::MB;
        $cw = self::CW;

        $title = $slots['title'] ?? '';
        $subtitle = $slots['subtitle'] ?? '';
        $body = $slots['body'] ?? '';
        $image = $slots['image_1'] ?? '';
        $caption = $slots['caption_1'] ?? '';
        $pullquote = $slots['pullquote'] ?? '';
        $attribution = $slots['attribution'] ?? '';
        $intro = $slots['intro'] ?? '';

        switch ($templateId) {

            // ═══════════════════════════════════════════
            // EDITORIAL SPREAD TEMPLATES (ed_*)
            // ═══════════════════════════════════════════

            // ─── COVER: Full-bleed image page ───
            case 'ed_cover_image':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                break;

            // ─── COVER: Title in vast white space ───
            case 'ed_cover_title':
                // 85% empty. Title centered vertically, tracked tight.
                if ($title) {
                    $blocks[] = $this->text($title, $ml + 40, $ph * 0.40, $cw - 80, 100, [
                        'fontSize' => 56, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textAlign' => 'left', 'textColor' => '#1a1a1a',
                        'lineHeight' => 1.05, 'letterSpacing' => -0.03,
                    ]);
                }
                // Thin accent line above title
                $blocks[] = $this->rect($ml + 40, $ph * 0.38, 50, 1, '#ccc');
                if ($subtitle) {
                    $blocks[] = $this->text($subtitle, $ml + 40, $ph * 0.56, $cw * 0.5, 25, [
                        'fontSize' => 10, 'textColor' => '#999',
                        'letterSpacing' => 0.15, 'textTransform' => 'uppercase',
                    ]);
                }
                break;

            // ─── TOC: Narrow strip, asymmetric ───
            case 'ed_toc':
                // TOC in narrow column (30% width, left-aligned)
                $tocW = $cw * 0.35;
                $y = $mt + 30;
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $tocW, 20, [
                        'fontSize' => 8, 'textColor' => '#aaa',
                        'letterSpacing' => 0.2, 'textTransform' => 'uppercase',
                    ]);
                    $y += 28;
                    $blocks[] = $this->rect($ml, $y - 6, 30, 1, '#ddd');
                }
                if ($body) {
                    $blocks[] = $this->text($body, $ml, $y, $tocW, $ph - $y - $mb, [
                        'fontSize' => 9, 'lineHeight' => 2.2, 'textColor' => '#333',
                        'fontFamily' => "'Inter', system-ui, sans-serif",
                    ]);
                }
                // Small thumbnail image in lower right
                if ($image !== '') {
                    $blocks[] = $this->image($image, $pw - $mr - 120, $ph - $mb - 120, 120, 120);
                }
                break;

            // ─── EDITOR'S NOTE: Narrow right-aligned column ───
            case 'ed_editors_note':
                $noteW = $cw * 0.45;
                $noteX = $pw - $mr - $noteW;
                $y = $mt + 60;
                if ($title) {
                    $blocks[] = $this->text($title, $noteX, $y, $noteW, 20, [
                        'fontSize' => 8, 'textColor' => '#aaa',
                        'letterSpacing' => 0.15, 'textTransform' => 'uppercase',
                    ]);
                    $y += 28;
                }
                if ($body) {
                    $blocks[] = $this->text($body, $noteX, $y, $noteW, $ph - $y - $mb, [
                        'fontSize' => 11, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontStyle' => 'italic', 'lineHeight' => 1.8, 'textColor' => '#444',
                    ]);
                }
                break;

            // ─── SECTION OPENER: Minimal — 90% empty ───
            case 'ed_section_opener_minimal':
                // Large section number as graphic element
                if ($title) {
                    // Section number/title — large, light grey
                    $blocks[] = $this->text($title, $ml, $ph * 0.55, $cw * 0.7, 80, [
                        'fontSize' => 120, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 300, 'textColor' => '#e8e4df',
                        'lineHeight' => 1.0,
                    ]);
                    // Title text smaller, overlaid
                    $blocks[] = $this->text($title, $ml, $ph * 0.68, $cw * 0.6, 40, [
                        'fontSize' => 32, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textColor' => '#1a1a1a', 'lineHeight' => 1.15,
                    ]);
                }
                if ($subtitle) {
                    $blocks[] = $this->rect($ml, $ph * 0.76, 40, 1, '#ccc');
                    $blocks[] = $this->text($subtitle, $ml, $ph * 0.78, $cw * 0.5, 30, [
                        'fontSize' => 11, 'textColor' => '#888', 'lineHeight' => 1.6,
                    ]);
                }
                break;

            // ─── FEATURE LEAD: Full-bleed with headline at bottom ───
            case 'ed_feature_lead_fullbleed':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                // Dark gradient overlay at bottom
                $blocks[] = $this->rect(0, $ph * 0.60, $pw, $ph * 0.40, 'rgba(0,0,0,0.55)');
                if ($title) {
                    $blocks[] = $this->text($title, $ml + 10, $ph * 0.68, $cw - 20, 90, [
                        'fontSize' => 36, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textColor' => '#ffffff',
                        'lineHeight' => 1.12,
                    ]);
                }
                break;

            // ─── FEATURE: Broken grid LEFT — two unequal columns, drop cap ───
            case 'ed_feature_broken_grid_left':
                $colNarrow = $cw * 0.35;
                $colWide = $cw * 0.55;
                $gap = $cw * 0.10;
                $y = $mt;

                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw, 65, [
                        'fontSize' => 28, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 78;
                    $blocks[] = $this->rect($ml, $y - 10, 40, 1, '#ddd');
                }

                if ($body) {
                    // Drop cap in narrow left column
                    $firstChar = mb_substr(strip_tags($body), 0, 1);
                    $blocks[] = $this->text($firstChar, $ml, $y, 48, 55, [
                        'fontSize' => 48, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textColor' => '#1a1a1a', 'lineHeight' => 1.0,
                    ]);

                    // Narrow left column (body starts after drop cap)
                    $blocks[] = $this->text(mb_substr($body, 1, 1200), $ml, $y + 55, $colNarrow, $ph - $y - 55 - $mb, [
                        'fontSize' => 10, 'lineHeight' => 1.7, 'textColor' => '#333',
                    ]);
                    // Wide right column
                    $blocks[] = $this->text(mb_substr($body, 1201, 1500), $ml + $colNarrow + $gap, $y, $colWide, $ph - $y - $mb, [
                        'fontSize' => 10.5, 'lineHeight' => 1.8, 'textColor' => '#333',
                    ]);
                }
                break;

            // ─── FEATURE: Broken grid RIGHT — wide column + inset image ───
            case 'ed_feature_broken_grid_right':
                $textW = $cw * 0.58;
                $imgW = $cw * 0.36;

                // Inset image top-right
                $imgH = min(260, $ph * 0.35);
                $blocks[] = $this->image($image, $pw - $mr - $imgW, $mt, $imgW, $imgH);
                if ($caption) {
                    $blocks[] = $this->text($caption, $pw - $mr - $imgW, $mt + $imgH + 4, $imgW, 16, [
                        'fontSize' => 7.5, 'textColor' => '#aaa', 'fontStyle' => 'italic',
                    ]);
                }

                // Text wraps around image
                if ($body) {
                    $blocks[] = $this->text($body, $ml, $mt, $textW, $ph - $mt - $mb, [
                        'fontSize' => 10.5, 'lineHeight' => 1.8, 'textColor' => '#333',
                    ]);
                }
                break;

            // ─── SILENCE: Just a thin centered rule — breathing page ───
            case 'ed_silence_rule':
                // 95% empty. Single thin rule, centered vertically.
                $blocks[] = $this->rect($pw * 0.42, $ph * 0.50, $pw * 0.16, 1, '#e0ddd8');
                break;

            // ─── PULLQUOTE: Centered in golden ratio ───
            case 'ed_pullquote_centered':
                if ($pullquote) {
                    // Decorative opening quote mark
                    $blocks[] = $this->text("\u{201C}", $pw * 0.44, $ph * 0.30, 50, 50, [
                        'fontSize' => 72, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'textColor' => '#e0ddd8', 'textAlign' => 'center', 'lineHeight' => 1.0,
                    ]);
                    // Quote text
                    $blocks[] = $this->text($pullquote, $ml + 60, $ph * 0.36, $cw - 120, 160, [
                        'fontSize' => 26, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'fontStyle' => 'italic', 'textAlign' => 'center',
                        'lineHeight' => 1.45, 'textColor' => '#222',
                    ]);
                }
                // Thin centered rule below
                $blocks[] = $this->rect($pw * 0.42, $ph * 0.56, $pw * 0.16, 1, '#ddd');
                if ($attribution) {
                    $blocks[] = $this->text($attribution, $ml, $ph * 0.59, $cw, 22, [
                        'fontSize' => 9, 'textAlign' => 'center', 'textColor' => '#bbb',
                        'letterSpacing' => 0.12, 'textTransform' => 'uppercase',
                    ]);
                }
                break;

            // ─── GRAND PULLQUOTE: Bigger, for crescendo ───
            case 'ed_grand_pullquote':
                // Thin rule above
                $blocks[] = $this->rect($pw * 0.40, $ph * 0.32, $pw * 0.20, 0.5, '#ccc');
                if ($pullquote) {
                    $blocks[] = $this->text($pullquote, $ml + 50, $ph * 0.36, $cw - 100, 200, [
                        'fontSize' => 30, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'fontStyle' => 'italic', 'textAlign' => 'center',
                        'lineHeight' => 1.40, 'textColor' => '#1a1a1a',
                    ]);
                }
                // Thin rule below
                $blocks[] = $this->rect($pw * 0.40, $ph * 0.62, $pw * 0.20, 0.5, '#ccc');
                break;

            // ─── FULL-PAGE IMAGE ───
            case 'ed_fullpage_image':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                break;

            // ─── FULL-BLEED IMAGE (same but used in different contexts) ───
            case 'ed_fullbleed_image':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                break;

            // ─── FULL-BLEED IMAGE + CAPTION ───
            case 'ed_fullbleed_image_caption':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph * 0.90);
                if ($caption) {
                    $blocks[] = $this->text($caption, $pw - $mr - 200, $ph * 0.92, 200, 18, [
                        'fontSize' => 7.5, 'textColor' => '#999', 'textAlign' => 'right',
                        'fontStyle' => 'italic',
                    ]);
                }
                break;

            // ─── TEXT: Right-aligned single column (30% left margin) ───
            case 'ed_text_right_aligned':
                $textW = $cw * 0.62;
                $textX = $ml + $cw * 0.30 + ($cw * 0.08);
                $y = $mt + 20;

                if ($title) {
                    $blocks[] = $this->text($title, $textX, $y, $textW, 60, [
                        'fontSize' => 24, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 72;
                    $blocks[] = $this->rect($textX, $y - 10, 40, 1, '#ddd');
                }
                if ($body) {
                    $blocks[] = $this->text($body, $textX, $y, $textW, $ph - $y - $mb, [
                        'fontSize' => 11, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            // ─── THREE COLUMN + IMAGE spanning cols 2-3 ───
            case 'ed_three_column_image':
                $gap = 16;
                $colW = ($cw - $gap * 2) / 3;
                $y = $mt;

                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw, 50, [
                        'fontSize' => 22, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 62;
                    $blocks[] = $this->rect($ml, $y - 8, 30, 1, '#ddd');
                }

                // Image spanning cols 2-3 at top
                $imgX = $ml + $colW + $gap;
                $imgW2 = $colW * 2 + $gap;
                $imgH = 200;
                $blocks[] = $this->image($image, $imgX, $y, $imgW2, $imgH);

                // Column 1: full text
                if ($body) {
                    $blocks[] = $this->text($body, $ml, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 9.5, 'lineHeight' => 1.65, 'textColor' => '#333',
                    ]);
                    // Columns 2-3: text below image
                    $blocks[] = $this->text(mb_substr($body, (int)(strlen($body) * 0.33), 1500),
                        $imgX, $y + $imgH + 12, $colW, $ph - $y - $imgH - 12 - $mb, [
                            'fontSize' => 9.5, 'lineHeight' => 1.65, 'textColor' => '#333',
                        ]);
                    $blocks[] = $this->text(mb_substr($body, (int)(strlen($body) * 0.66), 1500),
                        $imgX + $colW + $gap, $y + $imgH + 12, $colW, $ph - $y - $imgH - 12 - $mb, [
                            'fontSize' => 9.5, 'lineHeight' => 1.65, 'textColor' => '#333',
                        ]);
                }
                break;

            // ─── THREE COLUMN: Marginalia (cols 1-2 text, col 3 pullquote) ───
            case 'ed_three_column_marginalia':
                $gap = 16;
                $colW = ($cw - $gap * 2) / 3;
                $y = $mt + 10;

                if ($body) {
                    $mid = $this->splitAtWord($body, (int)(strlen($body) / 2));
                    // Col 1
                    $blocks[] = $this->text(substr($body, 0, $mid), $ml, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 9.5, 'lineHeight' => 1.65, 'textColor' => '#333',
                    ]);
                    // Col 2
                    $blocks[] = $this->text(substr($body, $mid), $ml + $colW + $gap, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 9.5, 'lineHeight' => 1.65, 'textColor' => '#333',
                    ]);
                }

                // Col 3: Marginalia / pullquote
                $marginX = $ml + ($colW + $gap) * 2;
                if ($pullquote) {
                    $blocks[] = $this->rect($marginX, $y + 20, 20, 1, '#ddd');
                    $blocks[] = $this->text($pullquote, $marginX, $y + 30, $colW, 180, [
                        'fontSize' => 13, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontStyle' => 'italic', 'lineHeight' => 1.55, 'textColor' => '#666',
                    ]);
                } else {
                    $blocks[] = $this->text('•', $marginX + $colW / 2, $ph * 0.4, 20, 20, [
                        'fontSize' => 14, 'textAlign' => 'center', 'textColor' => '#ddd',
                    ]);
                }
                break;

            // ─── INTERLUDE LEFT: Abstract image + scattered fragment ───
            case 'ed_interlude_left':
                // Circle-cropped placeholder image, off-center
                $blocks[] = $this->image($image, $ml + 30, $ph * 0.25, 150, 150);

                // Fragment 1 — large, tilted feel (we can't rotate, but position irregularly)
                if ($title) {
                    $blocks[] = $this->text($title, $ml + 200, $ph * 0.18, $cw * 0.5, 40, [
                        'fontSize' => 22, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textColor' => '#333', 'lineHeight' => 1.2,
                    ]);
                }
                // Fragment 2 — smaller, different position
                if ($subtitle) {
                    $blocks[] = $this->text($subtitle, $ml + 60, $ph * 0.58, $cw * 0.4, 30, [
                        'fontSize' => 14, 'textColor' => '#888', 'fontStyle' => 'italic',
                        'lineHeight' => 1.4,
                    ]);
                }
                break;

            // ─── INTERLUDE RIGHT: More fragments, poetic layout ───
            case 'ed_interlude_right':
                // Fragment positioned high-right
                if ($title) {
                    $blocks[] = $this->text($title, $pw - $mr - $cw * 0.45, $ph * 0.22, $cw * 0.40, 35, [
                        'fontSize' => 9, 'textColor' => '#aaa',
                        'letterSpacing' => 0.2, 'textTransform' => 'uppercase',
                    ]);
                }
                // Longer fragment low-left
                if ($subtitle) {
                    $blocks[] = $this->text($subtitle, $ml + 20, $ph * 0.65, $cw * 0.35, 30, [
                        'fontSize' => 18, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'textColor' => '#1a1a1a', 'lineHeight' => 1.3,
                    ]);
                }
                // Small body excerpt centered
                if ($body) {
                    $blocks[] = $this->text($body, $ml + $cw * 0.25, $ph * 0.42, $cw * 0.5, 80, [
                        'fontSize' => 10, 'textAlign' => 'center', 'textColor' => '#999',
                        'lineHeight' => 1.8, 'fontStyle' => 'italic',
                    ]);
                }
                break;

            // ─── ASYMMETRIC: Image 60% + text wraps ───
            case 'ed_asymmetric_image_text':
                // Image: top-left 60%
                $imgW = $cw * 0.60;
                $imgH = $ph * 0.50;
                $blocks[] = $this->image($image, $ml, $mt, $imgW, $imgH);

                // Title tight against image edge
                if ($title) {
                    $blocks[] = $this->text($title, $ml + $imgW + 16, $mt, $cw - $imgW - 16, 80, [
                        'fontSize' => 20, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                }

                // Text below image, full width
                if ($body) {
                    $textY = $mt + $imgH + 16;
                    $blocks[] = $this->text($body, $ml, $textY, $cw, $ph - $textY - $mb, [
                        'fontSize' => 10.5, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            // ─── TWO COLUMN + MARGIN PULLQUOTE ───
            case 'ed_two_col_margin_quote':
                $gap = 20;
                $mainW = $cw * 0.70;
                $marginW = $cw * 0.24;
                $colW = ($mainW - $gap) / 2;
                $y = $mt + 10;

                if ($body) {
                    $mid = $this->splitAtWord($body, (int)(strlen($body) / 2));
                    $blocks[] = $this->text(substr($body, 0, $mid), $ml, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 10, 'lineHeight' => 1.7, 'textColor' => '#333',
                    ]);
                    $blocks[] = $this->text(substr($body, $mid), $ml + $colW + $gap, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 10, 'lineHeight' => 1.7, 'textColor' => '#333',
                    ]);
                }

                // Margin pullquote (right edge)
                $mqX = $ml + $mainW + 16;
                if ($pullquote) {
                    $blocks[] = $this->rect($mqX, $y + 40, 1, 100, '#ddd'); // vertical rule
                    $blocks[] = $this->text($pullquote, $mqX + 10, $y + 50, $marginW - 10, 160, [
                        'fontSize' => 14, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontStyle' => 'italic', 'lineHeight' => 1.5, 'textColor' => '#555',
                    ]);
                }
                break;

            // ─── CLOSING THOUGHT: Centered in bottom third ───
            case 'ed_closing_thought':
                $y = $ph * 0.60;
                if ($pullquote) {
                    $blocks[] = $this->text($pullquote, $ml + $cw * 0.15, $y, $cw * 0.70, 80, [
                        'fontSize' => 16, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontStyle' => 'italic', 'textAlign' => 'center', 'textColor' => '#555',
                        'lineHeight' => 1.5,
                    ]);
                    $y += 100;
                }
                $blocks[] = $this->rect($pw * 0.44, $y, $pw * 0.12, 1, '#ddd');
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y + 16, $cw, 24, [
                        'fontSize' => 12, 'textAlign' => 'center', 'textColor' => '#aaa',
                        'letterSpacing' => 0.08,
                    ]);
                }
                break;

            // ─── COLOPHON: Credits at very bottom ───
            case 'ed_colophon':
                // Almost entirely empty
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $ph * 0.45, $cw, 20, [
                        'fontSize' => 10, 'textAlign' => 'center', 'textColor' => '#ddd',
                        'letterSpacing' => 0.15,
                    ]);
                }
                // Colophon at very bottom
                if ($body) {
                    $blocks[] = $this->text($body, $ml + $cw * 0.2, $ph - $mb - 20, $cw * 0.6, 18, [
                        'fontSize' => 7, 'textAlign' => 'center', 'textColor' => '#ccc',
                    ]);
                }
                break;

            // ═══════════════════════════════════════════
            // LEGACY TEMPLATES (for backward compatibility)
            // ═══════════════════════════════════════════

            case 'cover_title_only':
                $blocks[] = $this->rect(0, 0, $pw, $ph, '#0a0a0a');
                $blocks[] = $this->rect($pw * 0.3, $ph * 0.28, $pw * 0.4, 1, '#333');
                if ($title) {
                    $blocks[] = $this->text($title, $ml + 20, $ph * 0.32, $cw - 40, 120, [
                        'fontSize' => 52, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textAlign' => 'center', 'textColor' => '#f5f5f0',
                        'lineHeight' => 1.1, 'letterSpacing' => -0.02,
                    ]);
                }
                if ($subtitle) {
                    $blocks[] = $this->text($subtitle, $ml + 60, $ph * 0.52, $cw - 120, 30, [
                        'fontSize' => 11, 'textAlign' => 'center', 'textColor' => '#888',
                        'letterSpacing' => 0.15, 'textTransform' => 'uppercase',
                    ]);
                }
                $blocks[] = $this->rect($pw * 0.3, $ph * 0.68, $pw * 0.4, 1, '#333');
                break;

            case 'cover_image_title':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                $blocks[] = $this->rect(0, $ph * 0.55, $pw, $ph * 0.45, 'rgba(0,0,0,0.6)');
                if ($title) {
                    $blocks[] = $this->text($title, $ml + 10, $ph * 0.62, $cw - 20, 100, [
                        'fontSize' => 48, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'textAlign' => 'left', 'textColor' => '#fff',
                        'lineHeight' => 1.1,
                    ]);
                }
                if ($subtitle) {
                    $blocks[] = $this->text($subtitle, $ml + 10, $ph * 0.78, $cw - 20, 25, [
                        'fontSize' => 12, 'textColor' => 'rgba(255,255,255,0.6)',
                        'letterSpacing' => 0.1, 'textTransform' => 'uppercase',
                    ]);
                }
                break;

            case 'chapter_opener_full_bleed':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph * 0.65);
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $ph * 0.72, $cw, 80, [
                        'fontSize' => 40, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.1,
                    ]);
                }
                if ($subtitle) {
                    $blocks[] = $this->text($subtitle, $ml, $ph * 0.85, $cw * 0.7, 40, [
                        'fontSize' => 13, 'textColor' => '#888', 'lineHeight' => 1.6,
                    ]);
                }
                break;

            case 'chapter_opener_quiet':
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $ph * 0.38, $cw, 80, [
                        'fontSize' => 36, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.15,
                    ]);
                }
                $blocks[] = $this->rect($ml, $ph * 0.50, 60, 1, '#ccc');
                if ($subtitle || $intro) {
                    $blocks[] = $this->text($intro ?: $subtitle, $ml, $ph * 0.54, $cw * 0.65, 120, [
                        'fontSize' => 13, 'textColor' => '#666', 'lineHeight' => 1.7,
                    ]);
                }
                break;

            case 'text_one_column':
                $textW = min($cw, 380);
                $textX = $ml + ($cw - $textW) / 2;
                $y = $mt;
                if ($title) {
                    $blocks[] = $this->text($title, $textX, $y, $textW, 70, [
                        'fontSize' => 28, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 85;
                    $blocks[] = $this->rect($textX, $y - 12, 40, 1, '#ddd');
                }
                if ($body) {
                    $blocks[] = $this->text($body, $textX, $y, $textW, $ph - $y - $mb, [
                        'fontSize' => 11, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            case 'text_two_column':
                $gap = 24;
                $colW = ($cw - $gap) / 2;
                $y = $mt;
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw, 60, [
                        'fontSize' => 26, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 75;
                    $blocks[] = $this->rect($ml, $y - 12, 40, 1, '#ddd');
                }
                if ($body) {
                    $mid = $this->splitAtWord($body, (int)(strlen($body) / 2));
                    $blocks[] = $this->text(substr($body, 0, $mid), $ml, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 10, 'lineHeight' => 1.7, 'textColor' => '#333',
                    ]);
                    $blocks[] = $this->text(substr($body, $mid), $ml + $colW + $gap, $y, $colW, $ph - $y - $mb, [
                        'fontSize' => 10, 'lineHeight' => 1.7, 'textColor' => '#333',
                    ]);
                }
                break;

            case 'text_with_side_image':
                $imgW = $cw * 0.42;
                $textW = $cw * 0.52;
                $y = $mt;
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw, 55, [
                        'fontSize' => 24, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 68;
                }
                $imgH = min($ph - $y - $mb, $imgW * 1.3);
                $blocks[] = $this->image($image, $ml + $textW + ($cw * 0.06), $y, $imgW, $imgH);
                if ($caption) {
                    $blocks[] = $this->text($caption, $ml + $textW + ($cw * 0.06), $y + $imgH + 6, $imgW, 20, [
                        'fontSize' => 8, 'textColor' => '#aaa', 'fontStyle' => 'italic',
                    ]);
                }
                if ($body) {
                    $blocks[] = $this->text($body, $ml, $y, $textW, $ph - $y - $mb, [
                        'fontSize' => 11, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            case 'text_with_top_image':
                $imgH = 300;
                $blocks[] = $this->image($image, $ml, $mt, $cw, $imgH);
                $y = $mt + $imgH + 10;
                if ($caption) {
                    $blocks[] = $this->text($caption, $ml, $y, $cw, 16, [
                        'fontSize' => 8, 'textColor' => '#aaa', 'fontStyle' => 'italic', 'textAlign' => 'right',
                    ]);
                    $y += 22;
                }
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw * 0.8, 55, [
                        'fontSize' => 24, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 62;
                }
                if ($body) {
                    $blocks[] = $this->text($body, $ml, $y, min($cw, 400), $ph - $y - $mb, [
                        'fontSize' => 11, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            case 'full_bleed_image':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                break;

            case 'full_bleed_image_caption':
                $blocks[] = $this->image($image, 0, 0, $pw, $ph * 0.88);
                if ($caption) {
                    $blocks[] = $this->text($caption, $ml, $ph * 0.91, $cw, 24, [
                        'fontSize' => 9, 'textColor' => '#999', 'textAlign' => 'center', 'fontStyle' => 'italic',
                    ]);
                }
                break;

            case 'pullquote_full_page':
                if ($pullquote) {
                    $blocks[] = $this->text("\u{201C}" . $pullquote . "\u{201D}", $ml + 50, $ph * 0.28, $cw - 100, 200, [
                        'fontSize' => 26, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'fontStyle' => 'italic', 'textAlign' => 'center',
                        'lineHeight' => 1.45, 'textColor' => '#222',
                    ]);
                }
                $blocks[] = $this->rect($pw * 0.42, $ph * 0.54, $pw * 0.16, 1, '#ddd');
                if ($attribution) {
                    $blocks[] = $this->text($attribution, $ml, $ph * 0.58, $cw, 25, [
                        'fontSize' => 10, 'textAlign' => 'center', 'textColor' => '#aaa',
                        'letterSpacing' => 0.1, 'textTransform' => 'uppercase',
                    ]);
                }
                break;

            case 'pullquote_with_text':
                $y = $mt + 20;
                if ($pullquote) {
                    $blocks[] = $this->text("\u{201C}" . $pullquote . "\u{201D}", $ml + 30, $y, $cw - 60, 120, [
                        'fontSize' => 22, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontStyle' => 'italic', 'textAlign' => 'center', 'lineHeight' => 1.45,
                    ]);
                    $y += 140;
                    $blocks[] = $this->rect($pw * 0.42, $y - 10, $pw * 0.16, 1, '#ddd');
                    $y += 10;
                }
                if ($body) {
                    $blocks[] = $this->text($body, $ml + 30, $y, $cw - 60, $ph - $y - $mb, [
                        'fontSize' => 11, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            case 'grid_2x2_images':
                $gap = 8;
                $iW = ($cw - $gap) / 2;
                $iH = ($ph - $mt - $mb - $gap) / 2;
                $imgs = [$image, $slots['image_2'] ?? '', $slots['image_3'] ?? '', $slots['image_4'] ?? ''];
                $blocks[] = $this->image($imgs[0], $ml, $mt, $iW, $iH);
                $blocks[] = $this->image($imgs[1], $ml + $iW + $gap, $mt, $iW, $iH);
                $blocks[] = $this->image($imgs[2], $ml, $mt + $iH + $gap, $iW, $iH);
                $blocks[] = $this->image($imgs[3], $ml + $iW + $gap, $mt + $iH + $gap, $iW, $iH);
                break;

            case 'grid_3_images_horizontal':
                $gap = 6;
                $iW = ($cw - $gap * 2) / 3;
                $iH = $ph * 0.5;
                $iY = ($ph - $iH) / 2;
                $imgs = [$image, $slots['image_2'] ?? '', $slots['image_3'] ?? ''];
                for ($i = 0; $i < 3; $i++) {
                    $blocks[] = $this->image($imgs[$i], $ml + $i * ($iW + $gap), $iY, $iW, $iH);
                }
                break;

            case 'interview_qa':
                $y = $mt;
                if ($image) {
                    $blocks[] = $this->image($image, $pw - $mr - 120, $y, 120, 120);
                }
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw - 140, 55, [
                        'fontSize' => 24, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontWeight' => 400, 'lineHeight' => 1.2,
                    ]);
                    $y += 65;
                }
                $blocks[] = $this->rect($ml, $y, 40, 1, '#ddd');
                $y += 15;
                if ($body) {
                    $blocks[] = $this->text($body, $ml, $y, $cw, $ph - $y - $mb, [
                        'fontSize' => 11, 'lineHeight' => 1.75, 'textColor' => '#333',
                    ]);
                }
                break;

            case 'visual_break_white':
                break;

            case 'visual_break_texture':
                if ($image) {
                    $blocks[] = $this->image($image, 0, 0, $pw, $ph);
                } else {
                    $blocks[] = $this->rect(0, 0, $pw, $ph, '#f5f3ef');
                    $blocks[] = $this->rect(0, $ph * 0.4, $pw, 1, '#e8e4df');
                }
                break;

            case 'closing_page':
                $y = $ph * 0.35;
                if ($pullquote) {
                    $blocks[] = $this->text("\u{201C}" . $pullquote . "\u{201D}", $ml + 40, $y, $cw - 80, 100, [
                        'fontSize' => 20, 'fontFamily' => "'Instrument Serif', Georgia, serif",
                        'fontStyle' => 'italic', 'textAlign' => 'center', 'lineHeight' => 1.45,
                        'textColor' => '#555',
                    ]);
                    $y += 120;
                }
                $blocks[] = $this->rect($pw * 0.44, $y, $pw * 0.12, 1, '#ddd');
                $y += 20;
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw, 30, [
                        'fontSize' => 14, 'textAlign' => 'center', 'textColor' => '#999',
                        'letterSpacing' => 0.08,
                    ]);
                }
                break;

            default:
                $y = $mt;
                if ($title) {
                    $blocks[] = $this->text($title, $ml, $y, $cw, 50, ['fontSize' => 24, 'fontFamily' => "'Instrument Serif', Georgia, serif"]);
                    $y += 60;
                }
                if ($image !== '') { $blocks[] = $this->image($image, $ml, $y, $cw, 250); $y += 260; }
                if ($body) { $blocks[] = $this->text($body, $ml, $y, min($cw, 400), $ph - $y - $mb, ['fontSize' => 11, 'lineHeight' => 1.75]); }
                break;
        }

        return $blocks;
    }

    // ─── Element builders ───

    private function text(string $content, float $x, float $y, float $w, float $h, array $typo = []): array
    {
        return [
            'type' => 'text_frame',
            'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
            'data' => [
                'content' => '<p>' . e($content) . '</p>',
                'overflow' => 'hidden', 'autoSize' => 'none', 'columnsInFrame' => 1,
                'columnGap' => 12, 'textInset' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                'verticalAlign' => 'top',
            ],
            'typography' => array_merge([
                'fontFamily' => "'Inter', system-ui, sans-serif",
                'fontSize' => 12, 'fontWeight' => 400, 'fontStyle' => 'normal',
                'lineHeight' => 1.6, 'textAlign' => 'left', 'textColor' => '#1a1a1a',
                'letterSpacing' => 0, 'textTransform' => 'none',
            ], $typo),
        ];
    }

    private function image(string $src, float $x, float $y, float $w, float $h): array
    {
        return [
            'type' => 'image_frame',
            'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
            'data' => [
                'src' => $src ?: '', 'alt' => '', 'fit' => 'cover',
                'focalPoint' => ['x' => 0.5, 'y' => 0.5],
            ],
            'style' => [
                'fill' => ['color' => self::PLACEHOLDER, 'opacity' => 1, 'gradient' => null],
                'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'],
                'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0],
                'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0,
            ],
        ];
    }

    private function rect(float $x, float $y, float $w, float $h, string $color): array
    {
        return [
            'type' => 'rectangle',
            'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
            'data' => ['fillColor' => $color],
            'style' => [
                'fill' => ['color' => $color, 'opacity' => 1, 'gradient' => null],
                'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'],
                'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0],
                'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0,
            ],
        ];
    }

    private function splitAtWord(string $text, int $near): int
    {
        if (strlen($text) <= $near) return strlen($text);
        $pos = $near;
        while ($pos > 0 && $text[$pos] !== ' ' && $text[$pos] !== "\n") $pos--;
        return $pos ?: $near;
    }
}
