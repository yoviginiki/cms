<?php

namespace Tests\Feature\Security;

use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Models\Theme;
use Tests\TestCase;

class XssTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
    }

    public function test_script_tags_stripped_from_text_blocks(): void
    {
        $sanitizer = app(SanitizationService::class);

        $block = Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'text',
            'data' => ['content' => '<p>Safe</p><script>alert("xss")</script><p>Also safe</p>'],
        ]);

        $result = $sanitizer->sanitizeBlock($block);

        $this->assertStringNotContainsString('<script>', $result['content']);
        $this->assertStringNotContainsString('alert', $result['content']);
        $this->assertStringContainsString('<p>Safe</p>', $result['content']);
    }

    public function test_event_handlers_stripped_from_html(): void
    {
        $sanitizer = app(SanitizationService::class);

        $block = Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'text',
            'data' => ['content' => '<p onclick="alert(1)" onmouseover="steal()">Click me</p>'],
        ]);

        $result = $sanitizer->sanitizeBlock($block);

        $this->assertStringNotContainsString('onclick', $result['content']);
        $this->assertStringNotContainsString('onmouseover', $result['content']);
        $this->assertStringContainsString('Click me', $result['content']);
    }

    public function test_javascript_urls_stripped(): void
    {
        $sanitizer = app(SanitizationService::class);

        $block = Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'text',
            'data' => ['content' => '<a href="javascript:alert(1)">Click</a>'],
        ]);

        $result = $sanitizer->sanitizeBlock($block);

        $this->assertStringNotContainsString('javascript:', $result['content']);
    }

    public function test_html_stripped_from_hero_title(): void
    {
        $sanitizer = app(SanitizationService::class);

        $block = Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'hero',
            'data' => ['title' => '<img src=x onerror=alert(1)>Hello', 'subtitle' => '<script>bad</script>World'],
        ]);

        $result = $sanitizer->sanitizeBlock($block);

        $this->assertStringNotContainsString('<img', $result['title']);
        $this->assertStringNotContainsString('<script>', $result['subtitle']);
        $this->assertStringContainsString('Hello', $result['title']);
        $this->assertStringContainsString('World', $result['subtitle']);
    }

    public function test_iframe_and_object_stripped(): void
    {
        $sanitizer = app(SanitizationService::class);

        $block = Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'text',
            'data' => ['content' => '<iframe src="evil.com"></iframe><object data="exploit"></object><embed src="bad">'],
        ]);

        $result = $sanitizer->sanitizeBlock($block);

        $this->assertStringNotContainsString('<iframe', $result['content']);
        $this->assertStringNotContainsString('<object', $result['content']);
        $this->assertStringNotContainsString('<embed', $result['content']);
    }

    public function test_stored_xss_not_rendered_in_published_html(): void
    {
        Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'text',
            'data' => ['content' => '<p>Safe</p><script>document.cookie</script>'],
            'order' => 0,
        ]);

        $theme = Theme::factory()->create(['site_id' => $this->site->id]);
        $this->site->update(['active_theme_id' => $theme->id]);

        $builder = app(BuildPageService::class);
        $html = $builder->build($this->page, $theme, $this->site);

        $this->assertStringNotContainsString('<script>document.cookie</script>', $html);
        $this->assertStringContainsString('Safe', $html);
    }
}
