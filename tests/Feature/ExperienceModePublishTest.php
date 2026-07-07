<?php

namespace Tests\Feature;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class ExperienceModePublishTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createPageWithSections(string $experienceMode = 'standard'): Page
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => $experienceMode,
            'status' => 'published',
        ]);

        // Add two section blocks
        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'],
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 1,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'],
        ]);

        return $page;
    }

    // ─── Standard page: zero Experience Mode artifacts ───

    public function test_standard_page_has_no_view_transition(): void
    {
        $page = $this->createPageWithSections('standard');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringNotContainsString('@view-transition', $html);
    }

    public function test_standard_page_has_no_experience_runtime(): void
    {
        $page = $this->createPageWithSections('standard');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringNotContainsString('experience-runtime', $html);
    }

    public function test_standard_page_has_no_experience_data_attributes(): void
    {
        $page = $this->createPageWithSections('standard');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringNotContainsString('data-experience-transition', $html);
        $this->assertStringNotContainsString('data-experience-enter', $html);
    }

    // ─── Cinematic page: has Experience Mode artifacts ───

    public function test_cinematic_page_has_view_transition(): void
    {
        $page = $this->createPageWithSections('cinematic');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringContainsString('@view-transition', $html);
        $this->assertStringContainsString('@supports (view-transition-name: none)', $html);
    }

    public function test_cinematic_page_has_experience_runtime_js(): void
    {
        $page = $this->createPageWithSections('cinematic');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertMatchesRegularExpression('#experience-runtime\.[a-f0-9]+\.js#', $html);
        $this->assertStringContainsString('defer', $html);
    }

    public function test_cinematic_page_has_experience_runtime_css(): void
    {
        $page = $this->createPageWithSections('cinematic');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertMatchesRegularExpression('#experience-runtime\.[a-f0-9]+\.css#', $html);
    }

    // ─── Cinematic page with experience attributes on sections ───

    public function test_cinematic_section_has_data_attributes(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => [
                'padding_top' => '40px',
                'padding_bottom' => '40px',
                'max_width' => '1200px',
                'experienceTransition' => 'slide-up',
                'experienceEnter' => 'stagger',
            ],
        ]);

        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringContainsString('data-experience-transition="slide-up"', $html);
        $this->assertStringContainsString('data-experience-enter="stagger"', $html);
    }

    // ─── Scene presets (v2 model) ───

    public function test_section_with_scene_preset_has_data_scene(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => [
                'padding_top' => '40px',
                'padding_bottom' => '40px',
                'max_width' => '1200px',
                'scene' => 'pinned-statement',
            ],
        ]);

        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringContainsString('data-scene="pinned-statement"', $html);
    }

    public function test_section_without_scene_has_no_data_scene(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'],
        ]);

        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringNotContainsString('data-scene=', $html);
    }

    public function test_standard_page_section_with_scene_still_no_runtime(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'standard',
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px', 'scene' => 'reveal'],
        ]);

        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        // Scene attribute is emitted (it's block data, always rendered)
        // But runtime is NOT injected (page is standard)
        $this->assertStringNotContainsString('experience-runtime.js', $html);
    }

    // ─── Pipeline integrity (Phase 5) ───

    public function test_standard_page_has_no_atmosphere_config(): void
    {
        $page = $this->createPageWithSections('standard');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringNotContainsString('experience-config', $html);
    }

    public function test_cinematic_page_has_atmosphere_config(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
            'seo_meta' => ['experience_preloader' => true, 'experience_cursor' => true],
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'],
        ]);

        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringContainsString('experience-config', $html);
        $this->assertStringContainsString('"preloader":true', $html);
        $this->assertStringContainsString('"cursor":true', $html);
    }

    public function test_standard_page_zero_cinematic_artifacts(): void
    {
        $page = $this->createPageWithSections('standard');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringNotContainsString('@view-transition', $html);
        $this->assertStringNotContainsString('experience-runtime.js', $html);
        $this->assertStringNotContainsString('experience-runtime.css', $html);
        $this->assertStringNotContainsString('experience-config', $html);
    }

    public function test_cinematic_page_has_all_artifacts(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
            'seo_meta' => ['experience_preloader' => false],
        ]);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'type' => 'section',
            'order' => 0,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px', 'scene' => 'reveal'],
        ]);

        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringContainsString('@view-transition', $html);
        $this->assertMatchesRegularExpression('#experience-runtime\.[a-f0-9]+\.js#', $html);
        $this->assertMatchesRegularExpression('#experience-runtime\.[a-f0-9]+\.css#', $html);
        $this->assertStringContainsString('experience-config', $html);
        $this->assertStringContainsString('data-scene="reveal"', $html);
    }
}
