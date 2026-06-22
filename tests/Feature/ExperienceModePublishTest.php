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

        $this->assertStringContainsString('experience-runtime.js', $html);
        $this->assertStringContainsString('defer', $html);
    }

    public function test_cinematic_page_has_experience_runtime_css(): void
    {
        $page = $this->createPageWithSections('cinematic');
        $html = app(BuildPageService::class)->build($page, $this->site->theme, $this->site);

        $this->assertStringContainsString('experience-runtime.css', $html);
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
}
