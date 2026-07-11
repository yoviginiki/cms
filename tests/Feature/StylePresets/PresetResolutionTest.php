<?php

namespace Tests\Feature\StylePresets;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Page;
use App\Models\Site;
use App\Models\StylePreset;
use Tests\TestCase;

/**
 * P3 resolution: a block links a style preset (preset_id) + local overrides;
 * the publish build resolves element preset → option-groups → local (last wins)
 * and compiles token refs ($color.accent → var(--color-accent)) to static CSS.
 */
class PresetResolutionTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function preset(array $style, string $kind = 'element'): StylePreset
    {
        return StylePreset::create([
            'site_id' => $this->site->id, 'block_type' => 'text', 'kind' => $kind,
            'name' => 'Preset', 'style' => $style,
        ]);
    }

    private function buildWith(array $node): string
    {
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [$node]);
        return app(BuildPageService::class)->build($page->fresh(), $this->site->theme, $this->site);
    }

    public function test_element_preset_style_is_applied_and_tokens_compiled(): void
    {
        $preset = $this->preset([
            'visual' => ['backgroundColor' => '$color.accent'],
            'spacing' => ['paddingTop' => '24px'],
        ]);

        $html = $this->buildWith([
            'type' => 'text', 'level' => 'module', 'order' => 0,
            'preset_id' => $preset->id, 'data' => ['content' => 'Hi'],
        ]);

        $this->assertStringContainsString('background-color:var(--color-accent)', $html);
        $this->assertStringContainsString('padding-top:24px', $html);
    }

    public function test_local_override_beats_the_preset(): void
    {
        $preset = $this->preset(['spacing' => ['paddingTop' => '24px'], 'visual' => ['backgroundColor' => '$color.accent']]);

        $html = $this->buildWith([
            'type' => 'text', 'level' => 'module', 'order' => 0,
            'preset_id' => $preset->id,
            'style' => ['spacing' => ['paddingTop' => '99px']], // local wins
            'data' => ['content' => 'Hi'],
        ]);

        $this->assertStringContainsString('padding-top:99px', $html);
        $this->assertStringNotContainsString('padding-top:24px', $html);
        // untouched preset key still applies
        $this->assertStringContainsString('background-color:var(--color-accent)', $html);
    }

    public function test_local_token_reference_compiles_without_any_preset(): void
    {
        $html = $this->buildWith([
            'type' => 'text', 'level' => 'module', 'order' => 0,
            'style' => ['visual' => ['backgroundColor' => '$color.brand']],
            'data' => ['content' => 'Hi'],
        ]);

        $this->assertStringContainsString('background-color:var(--color-brand)', $html);
    }

    public function test_option_group_presets_stack_under_local(): void
    {
        $spacing = $this->preset(['spacing' => ['paddingTop' => '10px', 'paddingBottom' => '10px']], 'group');
        $typo = $this->preset(['typography' => ['fontWeight' => '700']], 'group');

        $html = $this->buildWith([
            'type' => 'text', 'level' => 'module', 'order' => 0,
            'style' => ['spacing' => ['paddingBottom' => '40px']], // local overrides one leaf
            'data' => ['content' => 'Hi', '__presetGroups' => [$spacing->id, $typo->id]],
        ]);

        $this->assertStringContainsString('padding-top:10px', $html);   // from spacing group
        $this->assertStringContainsString('padding-bottom:40px', $html); // local wins
        $this->assertStringContainsString('font-weight:700', $html);     // from typography group
    }
}
