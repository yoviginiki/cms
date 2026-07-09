<?php

namespace Tests\Unit\Services;

use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
use Tests\TestCase;

class SanitizationServiceTest extends TestCase
{
    private function sanitize(string $type, array $data): array
    {
        $block = new Block();
        $block->type = $type;
        $block->data = $data;

        return app(SanitizationService::class)->sanitizeBlock($block);
    }

    public function test_sanitizes_text_block_allows_safe_html(): void
    {
        $out = $this->sanitize('text', ['content' => '<strong>bold</strong> and <em>italic</em>']);
        $this->assertStringContainsString('<strong>bold</strong>', $out['content']);
        $this->assertStringContainsString('<em>italic</em>', $out['content']);
    }

    public function test_sanitizes_text_block_strips_scripts(): void
    {
        $out = $this->sanitize('text', ['content' => 'hi<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script', $out['content']);
        $this->assertStringContainsString('hi', $out['content']);
    }

    public function test_sanitizes_hero_block_strips_all_html(): void
    {
        $out = $this->sanitize('hero', ['title' => '<b>Big</b> Title', 'heading' => '<em>x</em>y']);
        $this->assertStringNotContainsString('<', $out['title']);
        $this->assertStringContainsString('Big Title', $out['title']);
    }

    public function test_allows_safe_urls(): void
    {
        $out = $this->sanitize('text', ['content' => '<a href="https://example.com">link</a>']);
        $this->assertStringContainsString('https://example.com', $out['content']);
    }

    public function test_strips_javascript_urls(): void
    {
        $out = $this->sanitize('text', ['content' => '<a href="javascript:alert(1)">x</a>']);
        $this->assertStringNotContainsString('javascript:', $out['content']);
    }
}
