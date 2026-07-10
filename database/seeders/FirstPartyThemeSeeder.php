<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T2 — first-party themes shipped as the default choice set for new sites.
 * Each is a hand-crafted W3C token document (theme.json). System themes
 * (site_id NULL, is_system true) — RLS is disabled around the writes, and the
 * seeder is idempotent (upsert by slug), so it is safe to re-run as themes are
 * added or refined.
 *
 * The set: Ensō (flagship), Journal, Ledger, Atelier, Hearth.
 */
class FirstPartyThemeSeeder extends Seeder
{
    public function run(): void
    {
        $pg = DB::connection()->getDriverName() === 'pgsql';
        if ($pg) DB::statement('ALTER TABLE themes DISABLE ROW LEVEL SECURITY');

        try {
            foreach ([$this->enso()] as $theme) {
                $this->upsert($theme);
            }
        } finally {
            if ($pg) DB::statement('ALTER TABLE themes ENABLE ROW LEVEL SECURITY');
        }
    }

    private function upsert(array $t): void
    {
        $existing = DB::table('themes')
            ->whereNull('site_id')->where('is_system', true)->where('slug', $t['slug'])
            ->first();

        $row = [
            'name' => $t['name'],
            'description' => $t['description'],
            'version' => '1.0.0',
            'manifest_json' => json_encode(['author' => 'Ensodo', 'first_party' => true]),
            'document' => json_encode($t['document']),
            'modes' => json_encode($t['modes']),
            'schema_version' => '1.0.0',
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('themes')->where('id', $existing->id)->update($row);
        } else {
            DB::table('themes')->insert($row + [
                'id' => (string) Str::uuid(),
                'site_id' => null,
                'slug' => $t['slug'],
                'config' => json_encode([]),
                'template_path' => '',
                'is_system' => true,
                'is_active' => false,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Ensō — the Stillopress house language: washi (warm paper) monochrome,
     * vermilion accent, Barlow Condensed display over Barlow body, zero radius,
     * no shadows. The circle drawn in one breath — restraint as the statement.
     */
    private function enso(): array
    {
        return [
            'name' => 'Ensō',
            'slug' => 'enso',
            'description' => 'The Stillopress flagship — washi monochrome, vermilion accent, Barlow Condensed/Barlow, zero radius, no shadow. Cinematic and quiet.',
            'modes' => ['light'],
            'document' => [
                '$metadata' => ['name' => 'Ensō', 'version' => '1.0.0', 'modes' => ['light'], 'author' => 'Ensodo'],
                'primitive' => [
                    'color' => [
                        'vermilion' => [
                            '500' => ['$type' => 'color', '$value' => '#E34234'],
                            '600' => ['$type' => 'color', '$value' => '#CE3324'],
                            '700' => ['$type' => 'color', '$value' => '#B12A1C'],
                        ],
                        // washi paper → sumi ink, warm neutral ramp
                        'washi' => [
                            '50'  => ['$type' => 'color', '$value' => '#FBFAF7'],
                            '100' => ['$type' => 'color', '$value' => '#F4F1EA'],
                            '200' => ['$type' => 'color', '$value' => '#E7E2D7'],
                            '300' => ['$type' => 'color', '$value' => '#D8D2C4'],
                            '500' => ['$type' => 'color', '$value' => '#9A9384'],
                            '600' => ['$type' => 'color', '$value' => '#57534A'],
                            '800' => ['$type' => 'color', '$value' => '#2A2723'],
                            '900' => ['$type' => 'color', '$value' => '#1A1817'],
                            '950' => ['$type' => 'color', '$value' => '#12100F'],
                        ],
                        'sage'  => ['500' => ['$type' => 'color', '$value' => '#5B7B62']],
                        'ochre' => ['500' => ['$type' => 'color', '$value' => '#C98A1E']],
                    ],
                    'font' => [
                        'family' => [
                            'barlow'    => ['$type' => 'fontFamily', '$value' => ['Barlow', '-apple-system', 'system-ui', 'sans-serif']],
                            'condensed' => ['$type' => 'fontFamily', '$value' => ['Barlow Condensed', 'Barlow', 'sans-serif']],
                            'mono'      => ['$type' => 'fontFamily', '$value' => ['ui-monospace', 'SFMono-Regular', 'monospace']],
                        ],
                    ],
                ],
                'semantic' => [
                    'color' => [
                        'brand'   => ['$type' => 'color', '$value' => '{primitive.color.vermilion.500}'],
                        'accent'  => ['$type' => 'color', '$value' => '{primitive.color.vermilion.700}'],
                        'success' => ['$type' => 'color', '$value' => '{primitive.color.sage.500}'],
                        'warning' => ['$type' => 'color', '$value' => '{primitive.color.ochre.500}'],
                        'danger'  => ['$type' => 'color', '$value' => '{primitive.color.vermilion.600}'],
                        'background' => [
                            'canvas'  => ['$type' => 'color', '$value' => '{primitive.color.washi.50}'],
                            'surface' => ['$type' => 'color', '$value' => '{primitive.color.washi.100}'],
                            'raised'  => ['$type' => 'color', '$value' => '#FFFFFF'],
                            'overlay' => ['$type' => 'color', '$value' => 'rgba(26,24,23,0.55)'],
                            'inverse' => ['$type' => 'color', '$value' => '{primitive.color.washi.900}'],
                        ],
                        'text' => [
                            'body'    => ['$type' => 'color', '$value' => '{primitive.color.washi.600}'],
                            'heading' => ['$type' => 'color', '$value' => '{primitive.color.washi.900}'],
                            'muted'   => ['$type' => 'color', '$value' => '{primitive.color.washi.500}'],
                            'link'    => ['$type' => 'color', '$value' => '{primitive.color.vermilion.700}'],
                            'inverse' => ['$type' => 'color', '$value' => '{primitive.color.washi.50}'],
                        ],
                        'border' => [
                            'subtle'  => ['$type' => 'color', '$value' => '{primitive.color.washi.100}'],
                            'default' => ['$type' => 'color', '$value' => '{primitive.color.washi.200}'],
                            'strong'  => ['$type' => 'color', '$value' => '{primitive.color.washi.500}'],
                        ],
                    ],
                    'text' => [
                        'decoration' => [
                            'link' => ['$type' => 'string', '$value' => 'none'],
                            'linkHover' => ['$type' => 'string', '$value' => 'underline'],
                        ],
                    ],
                    'font' => [
                        'family' => [
                            'display' => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.condensed}'],
                            'body'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.barlow}'],
                            'mono'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.mono}'],
                            'nav'     => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.condensed}'],
                            'button'  => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.condensed}'],
                        ],
                        // editorial scale — condensed display runs large and tight
                        'size' => [
                            'xs'  => ['$type' => 'dimension', '$value' => '0.72rem'],
                            'sm'  => ['$type' => 'dimension', '$value' => '0.86rem'],
                            'base'=> ['$type' => 'dimension', '$value' => '1.0625rem'],
                            'lg'  => ['$type' => 'dimension', '$value' => '1.32rem'],
                            'xl'  => ['$type' => 'dimension', '$value' => '1.6rem'],
                            '2xl' => ['$type' => 'dimension', '$value' => '2.1rem'],
                            '3xl' => ['$type' => 'dimension', '$value' => '2.9rem'],
                            '4xl' => ['$type' => 'dimension', '$value' => '3.8rem'],
                            '5xl' => ['$type' => 'dimension', '$value' => '5rem'],
                        ],
                        'lineHeight' => [
                            'body'    => ['$type' => 'number', '$value' => '1.66'],
                            'heading' => ['$type' => 'number', '$value' => '1.02'],
                        ],
                        'letterSpacing' => [
                            'body'    => ['$type' => 'dimension', '$value' => '0'],
                            'heading' => ['$type' => 'dimension', '$value' => '-0.01em'],
                        ],
                        'weight' => [
                            'heading' => ['$type' => 'number', '$value' => '600'],
                        ],
                    ],
                    'size' => [
                        'space' => [
                            'section'   => ['$type' => 'dimension', '$value' => 'clamp(60px, 8.5vw, 128px)'],
                            'container' => ['$type' => 'dimension', '$value' => '1280px'],
                            'gap'       => ['$type' => 'dimension', '$value' => 'clamp(22px, 2.6vw, 38px)'],
                        ],
                        'radius' => [
                            'none' => ['$type' => 'dimension', '$value' => '0'],
                            'sm'   => ['$type' => 'dimension', '$value' => '0'],
                            'md'   => ['$type' => 'dimension', '$value' => '0'],
                            'lg'   => ['$type' => 'dimension', '$value' => '0'],
                            'xl'   => ['$type' => 'dimension', '$value' => '0'],
                            'full' => ['$type' => 'dimension', '$value' => '0'],
                        ],
                    ],
                    'shadow' => [
                        'sm' => ['$type' => 'shadow', '$value' => 'none'],
                        'md' => ['$type' => 'shadow', '$value' => 'none'],
                        'lg' => ['$type' => 'shadow', '$value' => 'none'],
                        'xl' => ['$type' => 'shadow', '$value' => 'none'],
                    ],
                    // buttons: vermilion block, paper text, zero radius, tracked caps
                    'btn' => [
                        'bg'        => ['$type' => 'color', '$value' => '{primitive.color.vermilion.500}'],
                        'color'     => ['$type' => 'color', '$value' => '{primitive.color.washi.50}'],
                        'border'    => ['$type' => 'color', '$value' => 'transparent'],
                        'hoverBg'   => ['$type' => 'color', '$value' => '{primitive.color.vermilion.700}'],
                        'hoverColor'=> ['$type' => 'color', '$value' => '{primitive.color.washi.50}'],
                        'padding'   => ['$type' => 'string', '$value' => '14px 30px'],
                        'fontWeight'=> ['$type' => 'number', '$value' => '600'],
                        'tracking'  => ['$type' => 'dimension', '$value' => '0.14em'],
                        'transform' => ['$type' => 'string', '$value' => 'uppercase'],
                        'radius'    => ['$type' => 'dimension', '$value' => '0'],
                    ],
                    // nav: condensed, tracked caps, no chrome
                    'nav' => [
                        'fontSize'      => ['$type' => 'dimension', '$value' => '13px'],
                        'fontWeight'    => ['$type' => 'number', '$value' => '600'],
                        'letterSpacing' => ['$type' => 'dimension', '$value' => '0.14em'],
                        'textTransform' => ['$type' => 'string', '$value' => 'uppercase'],
                        'gap'           => ['$type' => 'dimension', '$value' => '30px'],
                        'logoSize'      => ['$type' => 'dimension', '$value' => '15px'],
                        'logoWeight'    => ['$type' => 'number', '$value' => '700'],
                        'logoTracking'  => ['$type' => 'dimension', '$value' => '0.12em'],
                        'padding'       => ['$type' => 'string', '$value' => '16px 0'],
                    ],
                    'footer' => [
                        'bg'          => ['$type' => 'color', '$value' => '{primitive.color.washi.900}'],
                        'color'       => ['$type' => 'color', '$value' => '{primitive.color.washi.300}'],
                        'borderColor' => ['$type' => 'color', '$value' => '{primitive.color.washi.800}'],
                    ],
                    'content' => [
                        'maxWidth'      => ['$type' => 'dimension', '$value' => '760px'],
                        'proseMaxWidth' => ['$type' => 'dimension', '$value' => '66ch'],
                    ],
                ],
            ],
        ];
    }
}
