<?php

namespace Database\Seeders;

use App\Support\Seeding\SystemRecordSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * System style presets (Builder P3 · owned by the Theme track). Ships a shared,
 * on-brand set of SYSTEM style presets (`style_presets`, site_id = NULL,
 * is_system = true) that every tenant can apply from the Style Presets panel.
 *
 * THEME-AGNOSTIC: every style value references a design token in `$path` form
 * (`$color.primary` → var(--color-primary)), so one preset library re-colours
 * across all five first-party themes and resolves at publish to static CSS
 * (StyleTokens compiles `$` → var(), BlockStyle safeColor/safeDim validate).
 * House style: zero radius, no shadows, Barlow/heading font, vermilion accent.
 *
 * is_default is intentionally FALSE on every preset — a shared library to apply,
 * not a silent global restyle of every tenant's new blocks. (Per-theme defaults
 * can be enabled later, coordinated with theme selection.)
 *
 * Idempotent upsert by (slug, site_id NULL). Reuses the ONE privileged-write
 * path, SystemRecordSeeder::withRlsDisabled, that the starter packs proved
 * (style_presets is FORCE ROW LEVEL SECURITY, so even the owner needs it).
 *
 *   php artisan db:seed --class=Database\\Seeders\\SystemStylePresetSeeder
 */
class SystemStylePresetSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();

        SystemRecordSeeder::withRlsDisabled('style_presets', function () use ($catalog) {
            foreach ($catalog as $p) {
                $this->upsert($p);
            }
        });

        $this->command?->info('Seeded ' . count($catalog) . ' system style presets.');
    }

    private function upsert(array $p): void
    {
        $existing = DB::table('style_presets')
            ->whereNull('site_id')->where('is_system', true)
            ->where('slug', $p['slug'])->first();

        $row = [
            'block_type' => $p['block_type'],
            'kind' => $p['kind'],
            'group' => $p['group'] ?? null,
            'name' => $p['name'],
            'style' => json_encode($p['style']),
            'is_default' => false,
            'sort' => $p['sort'] ?? 0,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('style_presets')->where('id', $existing->id)->update($row);
        } else {
            DB::table('style_presets')->insert($row + [
                'id' => (string) Str::uuid(),
                'site_id' => null,
                'slug' => $p['slug'],
                'is_system' => true,
                'created_at' => now(),
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function catalog(): array
    {
        return [
            // ── Element presets ──
            [
                'slug' => 'sys-button-primary', 'name' => 'Primary', 'block_type' => 'button', 'kind' => 'element', 'sort' => 0,
                'style' => [
                    'typography' => ['fontFamily' => '$font.heading', 'fontWeight' => 600, 'textTransform' => 'uppercase', 'letterSpacing' => '0.08em', 'textColor' => '$color.bg'],
                    'visual' => ['backgroundColor' => '$color.primary', 'borderWidth' => '1px', 'borderStyle' => 'solid', 'borderColor' => '$color.primary', 'borderRadius' => '0', 'boxShadow' => 'none'],
                    'spacing' => ['paddingTop' => '0.9em', 'paddingBottom' => '0.9em', 'paddingLeft' => '1.6em', 'paddingRight' => '1.6em'],
                ],
            ],
            [
                'slug' => 'sys-button-outline', 'name' => 'Outline', 'block_type' => 'button', 'kind' => 'element', 'sort' => 1,
                'style' => [
                    'typography' => ['fontFamily' => '$font.heading', 'fontWeight' => 600, 'textTransform' => 'uppercase', 'letterSpacing' => '0.08em', 'textColor' => '$color.text'],
                    'visual' => ['backgroundColor' => 'transparent', 'borderWidth' => '1px', 'borderStyle' => 'solid', 'borderColor' => '$color.border', 'borderRadius' => '0', 'boxShadow' => 'none'],
                    'spacing' => ['paddingTop' => '0.9em', 'paddingBottom' => '0.9em', 'paddingLeft' => '1.6em', 'paddingRight' => '1.6em'],
                ],
            ],
            [
                'slug' => 'sys-heading-display', 'name' => 'Display', 'block_type' => 'heading', 'kind' => 'element', 'sort' => 0,
                'style' => [
                    'typography' => ['fontFamily' => '$font.heading', 'fontWeight' => 600, 'letterSpacing' => '-0.02em', 'lineHeight' => '1.05', 'textColor' => '$color.heading'],
                ],
            ],
            [
                'slug' => 'sys-heading-eyebrow', 'name' => 'Eyebrow', 'block_type' => 'heading', 'kind' => 'element', 'sort' => 1,
                'style' => [
                    'typography' => ['fontFamily' => '$font.heading', 'fontWeight' => 600, 'textTransform' => 'uppercase', 'letterSpacing' => '0.2em', 'fontSize' => '0.78rem', 'textColor' => '$color.text-muted'],
                ],
            ],
            [
                'slug' => 'sys-paragraph-body', 'name' => 'Body', 'block_type' => 'paragraph', 'kind' => 'element', 'sort' => 0,
                'style' => [
                    'typography' => ['lineHeight' => '1.6', 'textColor' => '$color.text'],
                ],
            ],
            [
                'slug' => 'sys-paragraph-lead', 'name' => 'Lead', 'block_type' => 'paragraph', 'kind' => 'element', 'sort' => 1,
                'style' => [
                    'typography' => ['fontSize' => '1.2rem', 'lineHeight' => '1.6', 'textColor' => '$color.text-muted'],
                ],
            ],
            [
                'slug' => 'sys-section-contained', 'name' => 'Contained', 'block_type' => 'section', 'kind' => 'element', 'sort' => 0,
                'style' => [
                    'spacing' => ['paddingTop' => '80px', 'paddingBottom' => '80px'],
                ],
            ],
            [
                'slug' => 'sys-section-alt', 'name' => 'Alternate surface', 'block_type' => 'section', 'kind' => 'element', 'sort' => 1,
                'style' => [
                    'visual' => ['backgroundColor' => '$color.bg-alt'],
                    'spacing' => ['paddingTop' => '80px', 'paddingBottom' => '80px'],
                ],
            ],

            // ── Option-group presets (stackable, any block) ──
            [
                'slug' => 'sys-group-label', 'name' => 'Uppercase label', 'block_type' => '*', 'kind' => 'group', 'group' => 'typography', 'sort' => 0,
                'style' => [
                    'typography' => ['fontFamily' => '$font.heading', 'fontWeight' => 600, 'textTransform' => 'uppercase', 'letterSpacing' => '0.16em'],
                ],
            ],
            [
                'slug' => 'sys-group-roomy', 'name' => 'Roomy spacing', 'block_type' => '*', 'kind' => 'group', 'group' => 'spacing', 'sort' => 1,
                'style' => [
                    'spacing' => ['paddingTop' => '64px', 'paddingBottom' => '64px'],
                ],
            ],
            [
                'slug' => 'sys-group-hairline', 'name' => 'Hairline border', 'block_type' => '*', 'kind' => 'group', 'group' => 'border', 'sort' => 2,
                'style' => [
                    'visual' => ['borderWidth' => '1px', 'borderStyle' => 'solid', 'borderColor' => '$color.border'],
                ],
            ],
        ];
    }
}
