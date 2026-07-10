<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T2 — five first-party themes, each a genuinely DIFFERENT design (not a
 * recolor): distinct palette, type system, radius/shadow character, AND a
 * `layout.style` that drives a structurally different sample page in the
 * theme picker preview. Shipped as the default choice set for new sites.
 *
 * System themes (site_id NULL, is_system true) — RLS disabled around writes,
 * idempotent (upsert by slug), safe to re-run.
 */
class FirstPartyThemeSeeder extends Seeder
{
    public function run(): void
    {
        $pg = DB::connection()->getDriverName() === 'pgsql';
        if ($pg) DB::statement('ALTER TABLE themes DISABLE ROW LEVEL SECURITY');
        try {
            foreach ([$this->enso(), $this->journal(), $this->ledger(), $this->atelier(), $this->hearth()] as $t) {
                $this->upsert($t);
            }
        } finally {
            if ($pg) DB::statement('ALTER TABLE themes ENABLE ROW LEVEL SECURITY');
        }
    }

    private function upsert(array $t): void
    {
        $existing = DB::table('themes')->whereNull('site_id')->where('is_system', true)->where('slug', $t['slug'])->first();
        $row = [
            'name' => $t['name'], 'description' => $t['description'], 'version' => '1.0.0',
            'manifest_json' => json_encode(['author' => 'Ensodo', 'first_party' => true, 'layout' => $t['document']['layout']['style'] ?? 'standard']),
            'document' => json_encode($t['document']), 'modes' => json_encode(['light']),
            'schema_version' => '1.0.0', 'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('themes')->where('id', $existing->id)->update($row);
        } else {
            DB::table('themes')->insert($row + [
                'id' => (string) Str::uuid(), 'site_id' => null, 'slug' => $t['slug'],
                'config' => json_encode([]), 'template_path' => '', 'is_system' => true,
                'is_active' => false, 'created_at' => now(),
            ]);
        }
    }

    /**
     * Build a full W3C token document from a compact spec so each theme reads
     * as a distinct design. Covers colors, fonts, scale, radius, shadow,
     * buttons, footer, and the layout personality.
     */
    private function compose(array $p): array
    {
        $c = $p['color'];
        $col = fn($v) => ['$type' => 'color', '$value' => $v];
        $dim = fn($v) => ['$type' => 'dimension', '$value' => $v];
        $num = fn($v) => ['$type' => 'number', '$value' => (string) $v];
        $fam = fn($v) => ['$type' => 'fontFamily', '$value' => $v];
        $btn = $p['btn'];

        return [
            '$metadata' => ['name' => $p['name'], 'version' => '1.0.0', 'modes' => ['light'], 'author' => 'Ensodo'],
            'layout' => ['style' => $p['layout']],
            'semantic' => [
                'color' => [
                    'brand' => $col($c['brand']), 'accent' => $col($c['accent'] ?? $c['brand']),
                    'success' => $col($c['success'] ?? '#3E7A54'), 'warning' => $col($c['warning'] ?? '#C98A1E'),
                    'danger' => $col($c['danger'] ?? $c['brand']),
                    'background' => [
                        'canvas' => $col($c['canvas']), 'surface' => $col($c['surface']),
                        'raised' => $col($c['raised'] ?? '#FFFFFF'), 'overlay' => $col('rgba(0,0,0,0.5)'),
                        'inverse' => $col($c['inverse']),
                    ],
                    'text' => [
                        'body' => $col($c['textBody']), 'heading' => $col($c['textHeading']),
                        'muted' => $col($c['textMuted']), 'link' => $col($c['link'] ?? $c['brand']),
                        'inverse' => $col($c['textInverse'] ?? '#FFFFFF'),
                    ],
                    'border' => [
                        'subtle' => $col($c['borderSubtle']), 'default' => $col($c['borderDefault']),
                        'strong' => $col($c['borderStrong']),
                    ],
                ],
                'font' => [
                    'family' => [
                        'display' => $fam($p['font']['display']), 'body' => $fam($p['font']['body']),
                        'mono' => $fam($p['font']['mono'] ?? ['ui-monospace', 'monospace']),
                        'nav' => $fam($p['font']['nav'] ?? $p['font']['display']),
                        'button' => $fam($p['font']['button'] ?? $p['font']['display']),
                    ],
                    'size' => array_map($dim, $p['scale']),
                    'lineHeight' => ['body' => $num($p['lh'][0]), 'heading' => $num($p['lh'][1])],
                    'letterSpacing' => ['body' => $dim('0'), 'heading' => $dim($p['track'] ?? '0')],
                    'weight' => ['heading' => $num($p['headingWeight'] ?? 700)],
                ],
                'size' => [
                    'space' => ['section' => $dim($p['section']), 'container' => $dim($p['container'] ?? '1280px'), 'gap' => $dim($p['gap'] ?? 'clamp(20px,2.4vw,32px)')],
                    'radius' => array_map($dim, $p['radius']),
                ],
                'shadow' => array_map(fn($v) => ['$type' => 'shadow', '$value' => $v], $p['shadow']),
                'btn' => [
                    'bg' => $col($btn['bg'] ?? $c['brand']), 'color' => $col($btn['color'] ?? '#FFFFFF'),
                    'border' => $col('transparent'), 'hoverBg' => $col($btn['hoverBg'] ?? ($c['accent'] ?? $c['brand'])),
                    'hoverColor' => $col($btn['color'] ?? '#FFFFFF'),
                    'padding' => ['$type' => 'string', '$value' => $btn['padding'] ?? '13px 26px'],
                    'fontWeight' => $num($btn['weight'] ?? 600),
                    'tracking' => $dim($btn['tracking'] ?? '0.02em'),
                    'transform' => ['$type' => 'string', '$value' => $btn['transform'] ?? 'none'],
                    'radius' => $dim($btn['radius'] ?? ($p['radius']['md'] ?? '0')),
                ],
                'footer' => [
                    'bg' => $col($c['footerBg'] ?? $c['inverse']), 'color' => $col($c['footerColor'] ?? $c['textMuted']),
                    'borderColor' => $col($c['borderDefault']),
                ],
                'content' => ['maxWidth' => $dim($p['contentMax'] ?? '760px'), 'proseMaxWidth' => $dim('66ch')],
            ],
        ];
    }

    private const ZERO_RADIUS = ['none' => '0', 'sm' => '0', 'md' => '0', 'lg' => '0', 'xl' => '0', 'full' => '0'];
    private const NO_SHADOW = ['sm' => 'none', 'md' => 'none', 'lg' => 'none', 'xl' => 'none'];
    private const SCALE_EDITORIAL = ['xs' => '0.72rem', 'sm' => '0.86rem', 'base' => '1.0625rem', 'lg' => '1.32rem', 'xl' => '1.6rem', '2xl' => '2.1rem', '3xl' => '2.9rem', '4xl' => '3.8rem', '5xl' => '5rem'];
    private const SCALE_UI = ['xs' => '0.75rem', 'sm' => '0.875rem', 'base' => '1rem', 'lg' => '1.2rem', 'xl' => '1.4rem', '2xl' => '1.8rem', '3xl' => '2.3rem', '4xl' => '3rem', '5xl' => '3.6rem'];

    private function enso(): array
    {
        return ['name' => 'Ensō', 'slug' => 'enso',
            'description' => 'Cinematic flagship — washi monochrome, vermilion accent, Barlow Condensed. Full-bleed, quiet, zero radius, no shadow.',
            'document' => $this->compose([
                'name' => 'Ensō', 'layout' => 'cinematic',
                'font' => ['display' => ['Barlow Condensed', 'sans-serif'], 'body' => ['Barlow', 'sans-serif']],
                'color' => [
                    'brand' => '#E34234', 'accent' => '#B12A1C',
                    'canvas' => '#FBFAF7', 'surface' => '#F4F1EA', 'inverse' => '#1A1817',
                    'textBody' => '#57534A', 'textHeading' => '#1A1817', 'textMuted' => '#9A9384', 'link' => '#B12A1C', 'textInverse' => '#FBFAF7',
                    'borderSubtle' => '#F4F1EA', 'borderDefault' => '#E7E2D7', 'borderStrong' => '#9A9384',
                    'footerBg' => '#1A1817', 'footerColor' => '#D8D2C4',
                ],
                'radius' => self::ZERO_RADIUS, 'shadow' => self::NO_SHADOW, 'scale' => self::SCALE_EDITORIAL,
                'lh' => ['1.66', '1.02'], 'track' => '-0.01em', 'headingWeight' => 600, 'section' => 'clamp(60px,8.5vw,128px)',
                'btn' => ['transform' => 'uppercase', 'tracking' => '0.14em', 'weight' => 600, 'padding' => '14px 30px', 'radius' => '0'],
            ])];
    }

    private function journal(): array
    {
        return ['name' => 'Journal', 'slug' => 'journal',
            'description' => 'Editorial magazine — Fraunces serif display, drop caps, multi-column feature wells and rule-separated stories. Cream, ink, burgundy.',
            'document' => $this->compose([
                'name' => 'Journal', 'layout' => 'magazine',
                'font' => ['display' => ['Fraunces', 'Georgia', 'serif'], 'body' => ['Newsreader', 'Georgia', 'serif'], 'nav' => ['Inter', 'sans-serif']],
                'color' => [
                    'brand' => '#8B1E2D', 'accent' => '#6E1622',
                    'canvas' => '#FDFBF6', 'surface' => '#F5F0E6', 'inverse' => '#20120F',
                    'textBody' => '#33261F', 'textHeading' => '#1A0F0C', 'textMuted' => '#8A7A6E', 'link' => '#8B1E2D',
                    'borderSubtle' => '#EFE7D9', 'borderDefault' => '#E3D9C7', 'borderStrong' => '#B7A78F',
                    'footerBg' => '#F5F0E6', 'footerColor' => '#8A7A6E',
                ],
                'radius' => self::ZERO_RADIUS, 'shadow' => self::NO_SHADOW,
                'scale' => ['xs' => '0.75rem', 'sm' => '0.9rem', 'base' => '1.125rem', 'lg' => '1.35rem', 'xl' => '1.7rem', '2xl' => '2.2rem', '3xl' => '3rem', '4xl' => '3.8rem', '5xl' => '4.6rem'],
                'lh' => ['1.7', '1.05'], 'track' => '-0.01em', 'headingWeight' => 500, 'section' => 'clamp(48px,6vw,88px)', 'contentMax' => '680px',
                'btn' => ['transform' => 'uppercase', 'tracking' => '0.12em', 'weight' => 600, 'radius' => '0'],
            ])];
    }

    private function ledger(): array
    {
        return ['name' => 'Ledger', 'slug' => 'ledger',
            'description' => 'Minimal business — Inter, tight structured grids, stat rows and feature cards. Confident, fast-scanning, subtle shadows, blue accent.',
            'document' => $this->compose([
                'name' => 'Ledger', 'layout' => 'business',
                'font' => ['display' => ['Space Grotesk', 'Inter', 'sans-serif'], 'body' => ['Inter', 'system-ui', 'sans-serif']],
                'color' => [
                    'brand' => '#2563EB', 'accent' => '#1D4ED8', 'success' => '#16A34A',
                    'canvas' => '#FFFFFF', 'surface' => '#F1F5FB', 'inverse' => '#0F172A',
                    'textBody' => '#334155', 'textHeading' => '#0F172A', 'textMuted' => '#64748B', 'link' => '#2563EB',
                    'borderSubtle' => '#EEF2F7', 'borderDefault' => '#E2E8F0', 'borderStrong' => '#CBD5E1',
                    'footerBg' => '#F8FAFC', 'footerColor' => '#64748B',
                ],
                'radius' => ['none' => '0', 'sm' => '4px', 'md' => '8px', 'lg' => '14px', 'xl' => '20px', 'full' => '999px'],
                'shadow' => ['sm' => '0 1px 2px rgba(16,24,40,0.06)', 'md' => '0 4px 12px rgba(16,24,40,0.08)', 'lg' => '0 12px 32px rgba(16,24,40,0.10)', 'xl' => '0 24px 48px rgba(16,24,40,0.12)'],
                'scale' => self::SCALE_UI, 'lh' => ['1.6', '1.1'], 'track' => '-0.02em', 'headingWeight' => 700, 'section' => 'clamp(56px,7vw,96px)',
                'btn' => ['transform' => 'none', 'tracking' => '0', 'weight' => 600, 'radius' => '8px', 'padding' => '12px 24px'],
            ])];
    }

    private function atelier(): array
    {
        return ['name' => 'Atelier', 'slug' => 'atelier',
            'description' => 'Portfolio — image-led, full-bleed galleries and huge project titles. Monochrome with a single accent, Archivo display, minimal text.',
            'document' => $this->compose([
                'name' => 'Atelier', 'layout' => 'portfolio',
                'font' => ['display' => ['Archivo', 'Helvetica Neue', 'sans-serif'], 'body' => ['Inter', 'sans-serif']],
                'color' => [
                    'brand' => '#111111', 'accent' => '#E4572E',
                    'canvas' => '#FFFFFF', 'surface' => '#F4F4F4', 'inverse' => '#111111',
                    'textBody' => '#3A3A3A', 'textHeading' => '#0A0A0A', 'textMuted' => '#8A8A8A', 'link' => '#E4572E',
                    'borderSubtle' => '#F0F0F0', 'borderDefault' => '#E4E4E4', 'borderStrong' => '#B4B4B4',
                    'footerBg' => '#FFFFFF', 'footerColor' => '#8A8A8A',
                ],
                'radius' => self::ZERO_RADIUS, 'shadow' => self::NO_SHADOW,
                'scale' => ['xs' => '0.72rem', 'sm' => '0.85rem', 'base' => '1rem', 'lg' => '1.3rem', 'xl' => '1.7rem', '2xl' => '2.3rem', '3xl' => '3.2rem', '4xl' => '4.4rem', '5xl' => '6rem'],
                'lh' => ['1.6', '0.95'], 'track' => '-0.03em', 'headingWeight' => 800, 'section' => 'clamp(56px,8vw,112px)',
                'btn' => ['bg' => '#111111', 'hoverBg' => '#E4572E', 'transform' => 'uppercase', 'tracking' => '0.1em', 'weight' => 600, 'radius' => '0'],
            ])];
    }

    private function hearth(): array
    {
        return ['name' => 'Hearth', 'slug' => 'hearth',
            'description' => 'Warm lifestyle — terracotta & sage, rounded corners, soft shadows and tag pills. Fraunces + Nunito Sans, recipe/list-friendly card rhythm.',
            'document' => $this->compose([
                'name' => 'Hearth', 'layout' => 'lifestyle',
                'font' => ['display' => ['Fraunces', 'Georgia', 'serif'], 'body' => ['Nunito Sans', 'system-ui', 'sans-serif']],
                'color' => [
                    'brand' => '#C96F4C', 'accent' => '#A85536', 'success' => '#7C8B5A', 'warning' => '#D8A24A',
                    'canvas' => '#FAF6F0', 'surface' => '#F3ECE2', 'inverse' => '#3A2F28',
                    'textBody' => '#6B5D50', 'textHeading' => '#3A2F28', 'textMuted' => '#9A8B7C', 'link' => '#A85536',
                    'borderSubtle' => '#F0E7DA', 'borderDefault' => '#E6DACB', 'borderStrong' => '#C9B8A5',
                    'footerBg' => '#3A2F28', 'footerColor' => '#D8CABB',
                ],
                'radius' => ['none' => '0', 'sm' => '8px', 'md' => '14px', 'lg' => '20px', 'xl' => '28px', 'full' => '999px'],
                'shadow' => ['sm' => '0 4px 16px rgba(120,90,60,0.08)', 'md' => '0 8px 30px rgba(120,90,60,0.10)', 'lg' => '0 16px 44px rgba(120,90,60,0.12)', 'xl' => '0 28px 60px rgba(120,90,60,0.14)'],
                'scale' => ['xs' => '0.75rem', 'sm' => '0.9rem', 'base' => '1.0625rem', 'lg' => '1.3rem', 'xl' => '1.6rem', '2xl' => '2rem', '3xl' => '2.6rem', '4xl' => '3.4rem', '5xl' => '4.2rem'],
                'lh' => ['1.7', '1.1'], 'track' => '0', 'headingWeight' => 600, 'section' => 'clamp(48px,6vw,88px)',
                'btn' => ['transform' => 'none', 'tracking' => '0.01em', 'weight' => 700, 'radius' => '999px', 'padding' => '13px 26px'],
            ])];
    }
}
