<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\LinkBlockDefinition;
use App\Support\Blocks\LinkIcons;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LinkBlockTest extends TestCase
{
    public function test_auto_icon_follows_the_url(): void
    {
        $this->assertSame('spotify', LinkIcons::resolve('auto', 'https://open.spotify.com/playlist/4fTOUKhxw5crPUWX9xvcGx'));
        $this->assertSame('youtube', LinkIcons::resolve('auto', 'https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('mail', LinkIcons::resolve('auto', 'mailto:a@b.c'));
        $this->assertSame('external', LinkIcons::resolve('auto', 'https://example.com'));
        $this->assertSame('arrow', LinkIcons::resolve('auto', '/portfolio/levke'));
        $this->assertNull(LinkIcons::resolve('none', 'https://example.com'));
        $this->assertSame('music', LinkIcons::resolve('music', 'https://example.com'));
    }

    public function test_safe_href(): void
    {
        $this->assertSame('/portfolio/levke', LinkIcons::safeHref('/portfolio/levke'));
        $this->assertSame('https://example.com/x', LinkIcons::safeHref('example.com/x'));
        $this->assertNull(LinkIcons::safeHref('javascript:alert(1)'));
        $this->assertNull(LinkIcons::safeHref('//evil.example'));
    }

    public function test_validation(): void
    {
        $rules = (new LinkBlockDefinition())->validationRules();
        $this->assertTrue(Validator::make(['icon' => 'spotify', 'linkType' => 'post', 'underline' => 'always'], $rules)->passes());
        $this->assertTrue(Validator::make(['icon' => 'rocket'], $rules)->fails());
        $this->assertTrue(Validator::make(['linkUrl' => 'javascript:alert(1)'], $rules)->fails());
    }

    public function test_renders_link_with_icon(): void
    {
        $html = view('blocks.link', ['data' => [
            'text' => 'Слушай в Spotify', 'linkUrl' => 'https://open.spotify.com/playlist/4fTOUKhxw5crPUWX9xvcGx',
            'linkTarget' => '_blank', 'icon' => 'auto', 'brandColor' => true,
        ]])->render();
        $this->assertStringContainsString('href="https://open.spotify.com/playlist/4fTOUKhxw5crPUWX9xvcGx"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('stroke="#1DB954"', $html);
        $this->assertStringContainsString('<span>Слушай в Spotify</span>', $html);
        // rendered twice in one process: the underline CSS must still be there (no @once)
        $this->assertStringContainsString('.link-block a{', view('blocks.link', ['data' => ['linkUrl' => '/x']])->render());
    }
}
