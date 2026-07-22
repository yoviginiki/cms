<?php

namespace Database\Seeders;

use App\Support\Seeding\SystemRecordSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * System Library entries for the interactive app-blocks (breathing pacer,
 * meditation timer, guided-coordination trainer, prompt-card deck). Each is a
 * ready-to-drop SYSTEM section (block_templates, site_id = NULL, is_system =
 * true) wrapping the native app-block with sensible defaults, so any tenant can
 * add a working, editable interactive widget from the Library.
 *
 * Behaviour ships via the self-hosted app-tools runtime (AppToolRender), which
 * publishes automatically on any page that contains one of these blocks.
 *
 * Idempotent: upserts by (slug, site_id NULL). Runs standalone via
 *   php artisan db:seed --class=Database\\Seeders\\AppToolSectionSeeder
 */
class AppToolSectionSeeder extends Seeder
{
    private int $rowOrder = 0;
    private int $colOrder = 0;
    private int $blockOrder = 0;

    public function run(): void
    {
        $catalog = $this->catalog();

        SystemRecordSeeder::withRlsDisabled('block_templates', function () use ($catalog) {
            foreach ($catalog as $item) {
                $this->upsert($item);
            }
        });

        $this->command?->info('Seeded ' . count($catalog) . ' interactive app-block sections.');
    }

    private function upsert(array $item): void
    {
        $existing = DB::table('block_templates')
            ->whereNull('site_id')->where('is_system', true)
            ->where('slug', $item['slug'])->first();

        $row = [
            'name' => $item['name'],
            'category' => $item['category'],
            'kind' => 'section',
            'tags' => json_encode(array_values(array_unique(array_merge(['interactive', 'app'], $item['tags'])))),
            'description' => $item['description'],
            'blocks_data' => json_encode($item['blocks']),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('block_templates')->where('id', $existing->id)->update($row);
        } else {
            DB::table('block_templates')->insert($row + [
                'id' => Str::uuid()->toString(),
                'site_id' => null,
                'slug' => $item['slug'],
                'is_system' => true,
                'preview_image' => null,
                'created_at' => now(),
            ]);
        }
    }

    private function catalog(): array
    {
        return [
            [
                'slug' => 'app-breathing-pacer',
                'name' => 'Breathing Pacer',
                'category' => 'Interactive',
                'tags' => ['breath', 'wellness', 'timer'],
                'description' => 'Animated breathing orb with adjustable phases, rounds and gentle audio cues.',
                'blocks' => [$this->wrap('breathing-pacer', [
                    'eyebrow' => 'Interactive practice',
                    'title' => 'Box breathing pacer',
                    'soundLabel' => 'Gentle cues',
                    'soundDefault' => true,
                    'advancedAt' => 20,
                    'defaultRounds' => 5,
                    'roundOptions' => [3, 5, 8],
                    'phases' => [
                        ['label' => 'Inhale', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => false],
                        ['label' => 'Hold gently', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => true],
                        ['label' => 'Exhale', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => true],
                        ['label' => 'Rest empty', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => true],
                    ],
                ])],
            ],
            [
                'slug' => 'app-meditation-timer',
                'name' => 'Meditation Timer',
                'category' => 'Interactive',
                'tags' => ['meditation', 'wellness', 'timer'],
                'description' => 'Progress-ring meditation timer with presets, a soft bell and optional day journeys.',
                'blocks' => [$this->wrap('meditation-timer', [
                    'eyebrow' => 'Practise now',
                    'title' => 'Zen meditation timer',
                    'presets' => [5, 10, 15, 20, 30, 45],
                    'defaultMinutes' => 5,
                    'showJourneys' => true,
                    'storeKey' => 'app-med',
                    'journeys' => [
                        '3-day opening' => [5, 10, 15],
                        '5-day steady' => [5, 8, 12, 15, 20],
                        '5-day deepening' => [10, 15, 20, 25, 30],
                    ],
                ])],
            ],
            [
                'slug' => 'app-guided-coordination',
                'name' => 'Guided Coordination',
                'category' => 'Interactive',
                'tags' => ['guided', 'wellness', 'exercise'],
                'description' => 'A phase-by-phase guided trainer that cycles cued steps for a set number of rounds.',
                'blocks' => [$this->wrap('pelvic-trainer', [
                    'eyebrow' => 'Guided coordination · 6 rounds',
                    'rounds' => 6,
                    'phases' => [
                        ['label' => 'Arrive', 'cue' => 'Feel the weight of the pelvis. Do nothing yet.', 'seconds' => 8],
                        ['label' => 'Inhale & widen', 'cue' => 'Let the lower ribs, belly and pelvic floor receive the breath.', 'seconds' => 5],
                        ['label' => 'Gentle lift', 'cue' => 'Lift at about 30% effort — no glute or abdominal squeeze.', 'seconds' => 3],
                        ['label' => 'Release fully', 'cue' => 'Let go for longer than you lifted. Notice the difference.', 'seconds' => 6],
                    ],
                ])],
            ],
            [
                'slug' => 'app-prompt-card-deck',
                'name' => 'Prompt Card Deck',
                'category' => 'Interactive',
                'tags' => ['cards', 'prompts', 'connection'],
                'description' => 'A deck of title/body prompt cards the visitor steps through one at a time.',
                'blocks' => [$this->wrap('partner-deck', [
                    'eyebrow' => 'Invitation, never obligation',
                    'buttonLabel' => 'Draw another',
                    'cards' => [
                        ['title' => 'Three-minute arrival', 'body' => 'Sit facing each other. Share one easy breathing rhythm for three minutes. No fixing, no performance, no finish.'],
                        ['title' => 'The touch map', 'body' => 'Each person shows three kinds of touch: yes, maybe and not today. Switch roles. Curiosity matters more than agreement.'],
                        ['title' => 'Pause is part of the dance', 'body' => 'Choose a neutral pause word. When it is heard: stop, take three easy exhales, then choose together how to continue.'],
                    ],
                ])],
            ],
        ];
    }

    /** Wrap one app-block in a section → row → column so it drops in as a section. */
    private function wrap(string $type, array $data): array
    {
        return $this->section([
            $this->row('1', [$this->column([$this->block($type, $data)])]),
        ], ['padding_top' => '48px', 'padding_bottom' => '48px', 'max_width' => '720px']);
    }

    private function section(array $children, array $data = [], ?array $style = null): array
    {
        $this->rowOrder = $this->colOrder = $this->blockOrder = 0;
        $node = [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => array_merge(['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'], $data),
            'children' => $children,
        ];
        if ($style) $node['style'] = $style;
        return $node;
    }

    private function row(string $layout, array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'row',
            'level' => 'row',
            'order' => $this->rowOrder++,
            'data' => ['layout' => $layout, 'gap' => '32px'],
            'children' => $children,
        ];
    }

    private function column(array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'column',
            'level' => 'column',
            'order' => $this->colOrder++,
            'data' => [],
            'children' => $children,
        ];
    }

    private function block(string $type, array $data, ?array $style = null): array
    {
        $node = [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'order' => $this->blockOrder++,
            'data' => $data,
            'children' => [],
        ];
        if ($style) $node['style'] = $style;
        return $node;
    }
}
