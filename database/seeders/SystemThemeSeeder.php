<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemThemeSeeder extends Seeder
{
    public function run(): void
    {
        // Temporarily disable RLS for system themes (site_id = NULL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE themes DISABLE ROW LEVEL SECURITY');
        }

        $themes = [
            $this->editorial(),
            $this->commerce(),
            $this->bare(),
            $this->cytechno(),
        ];

        foreach ($themes as $data) {
            // Use raw query to bypass RLS for system themes (tenant_id = NULL)
            $existing = DB::table('themes')
                ->whereNull('site_id')
                ->where('is_system', true)
                ->where('slug', $data['slug'])
                ->first();

            if ($existing) {
                DB::table('themes')
                    ->where('id', $existing->id)
                    ->update([
                        'document' => json_encode($data['document']),
                        'modes' => json_encode($data['modes']),
                        'schema_version' => '1.0.0',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('themes')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'site_id' => null,
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'],
                    'version' => '1.0.0',
                    'config' => json_encode([]),
                    'manifest_json' => json_encode([]),
                    'template_path' => '',
                    'document' => json_encode($data['document']),
                    'modes' => json_encode($data['modes']),
                    'schema_version' => '1.0.0',
                    'is_system' => true,
                    'is_active' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE themes ENABLE ROW LEVEL SECURITY');
        }
    }

    private function editorial(): array
    {
        return [
            'name' => 'Editorial',
            'slug' => 'editorial',
            'description' => 'Magazine-first theme with serif display type and generous whitespace.',
            'modes' => ['light', 'dark'],
            'document' => [
                '$metadata' => ['name' => 'Editorial', 'version' => '1.0.0', 'modes' => ['light', 'dark']],
                'primitive' => [
                    'color' => [
                        'neutral' => [
                            '50'  => ['$type' => 'color', '$value' => '#FAFAF9'],
                            '100' => ['$type' => 'color', '$value' => '#F5F5F4'],
                            '200' => ['$type' => 'color', '$value' => '#E7E5E4'],
                            '300' => ['$type' => 'color', '$value' => '#D6D3D1'],
                            '500' => ['$type' => 'color', '$value' => '#78716C'],
                            '700' => ['$type' => 'color', '$value' => '#44403C'],
                            '800' => ['$type' => 'color', '$value' => '#292524'],
                            '900' => ['$type' => 'color', '$value' => '#1C1917'],
                            '950' => ['$type' => 'color', '$value' => '#0C0A09'],
                        ],
                        'blue' => [
                            '500' => ['$type' => 'color', '$value' => '#1B6DF5'],
                            '600' => ['$type' => 'color', '$value' => '#2563EB'],
                        ],
                        'green'  => ['500' => ['$type' => 'color', '$value' => '#22C55E']],
                        'yellow' => ['500' => ['$type' => 'color', '$value' => '#EAB308']],
                        'red'    => ['500' => ['$type' => 'color', '$value' => '#EF4444']],
                    ],
                    'font' => [
                        'family' => [
                            'inter'    => ['$type' => 'fontFamily', '$value' => ['Inter', 'system-ui', 'sans-serif']],
                            'fraunces' => ['$type' => 'fontFamily', '$value' => ['Fraunces', 'Georgia', 'serif']],
                            'mono'     => ['$type' => 'fontFamily', '$value' => ['JetBrains Mono', 'monospace']],
                        ],
                    ],
                    'size' => [
                        '0'  => ['$type' => 'dimension', '$value' => '0'],
                        '1'  => ['$type' => 'dimension', '$value' => '0.25rem'],
                        '2'  => ['$type' => 'dimension', '$value' => '0.5rem'],
                        '3'  => ['$type' => 'dimension', '$value' => '0.75rem'],
                        '4'  => ['$type' => 'dimension', '$value' => '1rem'],
                        '6'  => ['$type' => 'dimension', '$value' => '1.5rem'],
                        '8'  => ['$type' => 'dimension', '$value' => '2rem'],
                        '12' => ['$type' => 'dimension', '$value' => '3rem'],
                        '16' => ['$type' => 'dimension', '$value' => '4rem'],
                        '24' => ['$type' => 'dimension', '$value' => '6rem'],
                    ],
                ],
                'semantic' => [
                    'color' => [
                        'brand'   => ['$type' => 'color', '$value' => '{primitive.color.blue.500}'],
                        'accent'  => ['$type' => 'color', '$value' => '{primitive.color.blue.600}'],
                        'success' => ['$type' => 'color', '$value' => '{primitive.color.green.500}'],
                        'warning' => ['$type' => 'color', '$value' => '{primitive.color.yellow.500}'],
                        'danger'  => ['$type' => 'color', '$value' => '{primitive.color.red.500}'],
                        'background' => [
                            'canvas'  => ['$type' => 'color', '$value' => '{primitive.color.neutral.50}'],
                            'surface' => ['$type' => 'color', '$value' => '{primitive.color.neutral.100}'],
                            'raised'  => ['$type' => 'color', '$value' => '#FFFFFF'],
                            'overlay' => ['$type' => 'color', '$value' => 'rgba(0,0,0,0.5)'],
                        ],
                        'text' => [
                            'body'    => ['$type' => 'color', '$value' => '{primitive.color.neutral.700}'],
                            'heading' => ['$type' => 'color', '$value' => '{primitive.color.neutral.900}'],
                            'muted'   => ['$type' => 'color', '$value' => '{primitive.color.neutral.500}'],
                            'link'    => ['$type' => 'color', '$value' => '{primitive.color.blue.600}'],
                        ],
                        'border' => [
                            'subtle'  => ['$type' => 'color', '$value' => '{primitive.color.neutral.200}'],
                            'default' => ['$type' => 'color', '$value' => '{primitive.color.neutral.300}'],
                            'strong'  => ['$type' => 'color', '$value' => '{primitive.color.neutral.500}'],
                        ],
                    ],
                    'font' => [
                        'family' => [
                            'display' => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.fraunces}'],
                            'body'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.inter}'],
                            'mono'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.mono}'],
                        ],
                        'size' => [
                            'xs'  => ['$type' => 'dimension', '$value' => '0.75rem'],
                            'sm'  => ['$type' => 'dimension', '$value' => '0.875rem'],
                            'base'=> ['$type' => 'dimension', '$value' => '1rem'],
                            'lg'  => ['$type' => 'dimension', '$value' => '1.125rem'],
                            'xl'  => ['$type' => 'dimension', '$value' => '1.25rem'],
                            '2xl' => ['$type' => 'dimension', '$value' => '1.5rem'],
                            '3xl' => ['$type' => 'dimension', '$value' => '1.875rem'],
                            '4xl' => ['$type' => 'dimension', '$value' => '2.25rem'],
                            '5xl' => ['$type' => 'dimension', '$value' => '3rem'],
                        ],
                    ],
                    'size' => [
                        'space' => [
                            'section' => ['$type' => 'dimension', '$value' => '{primitive.size.24}'],
                        ],
                        'radius' => [
                            'none' => ['$type' => 'dimension', '$value' => '0'],
                            'sm'   => ['$type' => 'dimension', '$value' => '0.25rem'],
                            'md'   => ['$type' => 'dimension', '$value' => '0.375rem'],
                            'lg'   => ['$type' => 'dimension', '$value' => '0.5rem'],
                            'xl'   => ['$type' => 'dimension', '$value' => '0.75rem'],
                            'full' => ['$type' => 'dimension', '$value' => '9999px'],
                        ],
                    ],
                    'shadow' => [
                        'sm' => ['$type' => 'shadow', '$value' => '0 1px 2px 0 rgba(0,0,0,0.05)'],
                        'md' => ['$type' => 'shadow', '$value' => '0 4px 6px -1px rgba(0,0,0,0.1)'],
                        'lg' => ['$type' => 'shadow', '$value' => '0 10px 15px -3px rgba(0,0,0,0.1)'],
                        'xl' => ['$type' => 'shadow', '$value' => '0 20px 25px -5px rgba(0,0,0,0.1)'],
                    ],
                ],
            ],
        ];
    }

    private function commerce(): array
    {
        return [
            'name' => 'Commerce',
            'slug' => 'commerce',
            'description' => 'Product-focused theme with tight spacing and vivid accents.',
            'modes' => ['light', 'dark'],
            'document' => [
                '$metadata' => ['name' => 'Commerce', 'version' => '1.0.0', 'modes' => ['light', 'dark']],
                'primitive' => [
                    'color' => [
                        'neutral' => [
                            '50'  => ['$type' => 'color', '$value' => '#F9FAFB'],
                            '100' => ['$type' => 'color', '$value' => '#F3F4F6'],
                            '200' => ['$type' => 'color', '$value' => '#E5E7EB'],
                            '500' => ['$type' => 'color', '$value' => '#6B7280'],
                            '700' => ['$type' => 'color', '$value' => '#374151'],
                            '900' => ['$type' => 'color', '$value' => '#111827'],
                            '950' => ['$type' => 'color', '$value' => '#030712'],
                        ],
                        'violet' => [
                            '500' => ['$type' => 'color', '$value' => '#8553F5'],
                            '600' => ['$type' => 'color', '$value' => '#7C3AED'],
                        ],
                        'green'  => ['500' => ['$type' => 'color', '$value' => '#10B981']],
                        'yellow' => ['500' => ['$type' => 'color', '$value' => '#A26907']],
                        'red'    => ['500' => ['$type' => 'color', '$value' => '#EF4444']],
                    ],
                    'font' => [
                        'family' => [
                            'inter' => ['$type' => 'fontFamily', '$value' => ['Inter', 'system-ui', 'sans-serif']],
                            'mono'  => ['$type' => 'fontFamily', '$value' => ['JetBrains Mono', 'monospace']],
                        ],
                    ],
                ],
                'semantic' => [
                    'color' => [
                        'brand'   => ['$type' => 'color', '$value' => '{primitive.color.violet.500}'],
                        'accent'  => ['$type' => 'color', '$value' => '{primitive.color.violet.600}'],
                        'success' => ['$type' => 'color', '$value' => '{primitive.color.green.500}'],
                        'warning' => ['$type' => 'color', '$value' => '{primitive.color.yellow.500}'],
                        'danger'  => ['$type' => 'color', '$value' => '{primitive.color.red.500}'],
                        'background' => [
                            'canvas'  => ['$type' => 'color', '$value' => '#FFFFFF'],
                            'surface' => ['$type' => 'color', '$value' => '{primitive.color.neutral.50}'],
                        ],
                        'text' => [
                            'body'    => ['$type' => 'color', '$value' => '{primitive.color.neutral.700}'],
                            'heading' => ['$type' => 'color', '$value' => '{primitive.color.neutral.900}'],
                            'muted'   => ['$type' => 'color', '$value' => '{primitive.color.neutral.500}'],
                        ],
                    ],
                    'font' => [
                        'family' => [
                            'display' => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.inter}'],
                            'body'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.inter}'],
                            'mono'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.mono}'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function bare(): array
    {
        return [
            'name' => 'Bare',
            'slug' => 'bare',
            'description' => 'Minimal utility theme. System fonts, neutral grays, square corners.',
            'modes' => ['light', 'dark'],
            'document' => [
                '$metadata' => ['name' => 'Bare', 'version' => '1.0.0', 'modes' => ['light', 'dark']],
                'primitive' => [
                    'color' => [
                        'neutral' => [
                            '50'  => ['$type' => 'color', '$value' => '#FAFAFA'],
                            '100' => ['$type' => 'color', '$value' => '#F4F4F5'],
                            '200' => ['$type' => 'color', '$value' => '#E4E4E7'],
                            '500' => ['$type' => 'color', '$value' => '#71717A'],
                            '700' => ['$type' => 'color', '$value' => '#3F3F46'],
                            '900' => ['$type' => 'color', '$value' => '#18181B'],
                            '950' => ['$type' => 'color', '$value' => '#09090B'],
                        ],
                        'blue' => ['500' => ['$type' => 'color', '$value' => '#1B6DF5']],
                        'green'  => ['500' => ['$type' => 'color', '$value' => '#22C55E']],
                        'yellow' => ['500' => ['$type' => 'color', '$value' => '#EAB308']],
                        'red'    => ['500' => ['$type' => 'color', '$value' => '#EF4444']],
                    ],
                    'font' => [
                        'family' => [
                            'system' => ['$type' => 'fontFamily', '$value' => ['system-ui', '-apple-system', 'sans-serif']],
                            'mono'   => ['$type' => 'fontFamily', '$value' => ['ui-monospace', 'monospace']],
                        ],
                    ],
                ],
                'semantic' => [
                    'color' => [
                        'brand'   => ['$type' => 'color', '$value' => '{primitive.color.blue.500}'],
                        'background' => [
                            'canvas'  => ['$type' => 'color', '$value' => '#FFFFFF'],
                            'surface' => ['$type' => 'color', '$value' => '{primitive.color.neutral.50}'],
                        ],
                        'text' => [
                            'body'    => ['$type' => 'color', '$value' => '{primitive.color.neutral.700}'],
                            'heading' => ['$type' => 'color', '$value' => '{primitive.color.neutral.900}'],
                            'muted'   => ['$type' => 'color', '$value' => '{primitive.color.neutral.500}'],
                        ],
                    ],
                    'font' => [
                        'family' => [
                            'display' => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.system}'],
                            'body'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.system}'],
                            'mono'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.mono}'],
                        ],
                    ],
                    'size' => [
                        'radius' => [
                            'none' => ['$type' => 'dimension', '$value' => '0'],
                            'sm'   => ['$type' => 'dimension', '$value' => '0'],
                            'md'   => ['$type' => 'dimension', '$value' => '0'],
                            'lg'   => ['$type' => 'dimension', '$value' => '0'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function cytechno(): array
    {
        return [
            'name' => 'Cytechno',
            'slug' => 'cytechno',
            'description' => 'Editorial-minimal Swiss/Bauhaus theme — monochrome with red accent, zero radius, no shadows. Barlow + Barlow Condensed.',
            'modes' => ['light'],
            'document' => [
                '$metadata' => ['name' => 'Cytechno', 'version' => '1.0.0', 'modes' => ['light']],
                'primitive' => [
                    'color' => [
                        'red'     => ['500' => ['$type' => 'color', '$value' => '#C81E1E'],
                                      '700' => ['$type' => 'color', '$value' => '#A81818']],
                        'neutral' => [
                            '50'  => ['$type' => 'color', '$value' => '#F1F1EF'],
                            '100' => ['$type' => 'color', '$value' => '#E4E3E1'],
                            '200' => ['$type' => 'color', '$value' => '#CDCCC9'],
                            '500' => ['$type' => 'color', '$value' => '#757575'],
                            '600' => ['$type' => 'color', '$value' => '#5B5B5B'],
                            '900' => ['$type' => 'color', '$value' => '#141414'],
                            '950' => ['$type' => 'color', '$value' => '#0E0E0E'],
                        ],
                        'green'  => ['500' => ['$type' => 'color', '$value' => '#22C55E']],
                        'yellow' => ['500' => ['$type' => 'color', '$value' => '#EAB308']],
                    ],
                    'font' => [
                        'family' => [
                            'barlow'    => ['$type' => 'fontFamily', '$value' => ['Barlow', '-apple-system', 'system-ui', 'sans-serif']],
                            'condensed' => ['$type' => 'fontFamily', '$value' => ['Barlow Condensed', 'sans-serif']],
                            'mono'      => ['$type' => 'fontFamily', '$value' => ['ui-monospace', 'monospace']],
                        ],
                    ],
                    'size' => [
                        '0'  => ['$type' => 'dimension', '$value' => '0'],
                        '1'  => ['$type' => 'dimension', '$value' => '0.25rem'],
                        '2'  => ['$type' => 'dimension', '$value' => '0.5rem'],
                        '3'  => ['$type' => 'dimension', '$value' => '0.75rem'],
                        '4'  => ['$type' => 'dimension', '$value' => '1rem'],
                        '6'  => ['$type' => 'dimension', '$value' => '1.5rem'],
                        '8'  => ['$type' => 'dimension', '$value' => '2rem'],
                        '12' => ['$type' => 'dimension', '$value' => '3rem'],
                        '16' => ['$type' => 'dimension', '$value' => '4rem'],
                        '24' => ['$type' => 'dimension', '$value' => '6rem'],
                    ],
                ],
                'semantic' => [
                    'color' => [
                        'brand'   => ['$type' => 'color', '$value' => '{primitive.color.red.500}'],
                        'accent'  => ['$type' => 'color', '$value' => '{primitive.color.red.700}'],
                        'success' => ['$type' => 'color', '$value' => '{primitive.color.green.500}'],
                        'warning' => ['$type' => 'color', '$value' => '{primitive.color.yellow.500}'],
                        'danger'  => ['$type' => 'color', '$value' => '{primitive.color.red.500}'],
                        'background' => [
                            'canvas'  => ['$type' => 'color', '$value' => '#FFFFFF'],
                            'surface' => ['$type' => 'color', '$value' => '{primitive.color.neutral.50}'],
                            'raised'  => ['$type' => 'color', '$value' => '#FFFFFF'],
                            'overlay' => ['$type' => 'color', '$value' => 'rgba(0,0,0,0.5)'],
                            'inverse' => ['$type' => 'color', '$value' => '{primitive.color.neutral.950}'],
                        ],
                        'text' => [
                            'body'    => ['$type' => 'color', '$value' => '{primitive.color.neutral.600}'],
                            'heading' => ['$type' => 'color', '$value' => '{primitive.color.neutral.900}'],
                            'muted'   => ['$type' => 'color', '$value' => '{primitive.color.neutral.500}'],
                            'link'    => ['$type' => 'color', '$value' => '{primitive.color.red.500}'],
                            'inverse' => ['$type' => 'color', '$value' => '#FFFFFF'],
                        ],
                        'border' => [
                            'subtle'  => ['$type' => 'color', '$value' => '{primitive.color.neutral.100}'],
                            'default' => ['$type' => 'color', '$value' => '{primitive.color.neutral.200}'],
                            'strong'  => ['$type' => 'color', '$value' => '{primitive.color.neutral.500}'],
                        ],
                    ],
                    'font' => [
                        'family' => [
                            'display' => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.condensed}'],
                            'body'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.barlow}'],
                            'mono'    => ['$type' => 'fontFamily', '$value' => '{primitive.font.family.mono}'],
                        ],
                        'size' => [
                            'xs'  => ['$type' => 'dimension', '$value' => '0.7rem'],
                            'sm'  => ['$type' => 'dimension', '$value' => '0.875rem'],
                            'base'=> ['$type' => 'dimension', '$value' => '1.0625rem'],
                            'lg'  => ['$type' => 'dimension', '$value' => '1.3rem'],
                            'xl'  => ['$type' => 'dimension', '$value' => '1.4rem'],
                            '2xl' => ['$type' => 'dimension', '$value' => '1.7rem'],
                            '3xl' => ['$type' => 'dimension', '$value' => '2rem'],
                            '4xl' => ['$type' => 'dimension', '$value' => '2.5rem'],
                            '5xl' => ['$type' => 'dimension', '$value' => '3.4rem'],
                        ],
                    ],
                    'size' => [
                        'space' => [
                            'section' => ['$type' => 'dimension', '$value' => 'clamp(56px, 8vw, 118px)'],
                            'container' => ['$type' => 'dimension', '$value' => '1320px'],
                            'gap'     => ['$type' => 'dimension', '$value' => 'clamp(20px, 2.4vw, 34px)'],
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
                ],
            ],
        ];
    }
}
