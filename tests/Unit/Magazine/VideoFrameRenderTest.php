<?php

namespace Tests\Unit\Magazine;

use App\Domain\Magazine\Services\DtpRenderService;
use PHPUnit\Framework\TestCase;

class VideoFrameRenderTest extends TestCase
{
    private function render(array $content): string
    {
        $svc = new \ReflectionClass(DtpRenderService::class);
        $m = $svc->getMethod('renderVideoFrame');
        $m->setAccessible(true);

        return $m->invoke($svc->newInstanceWithoutConstructor(), $content);
    }

    public function test_no_url_renders_placeholder(): void
    {
        $this->assertStringContainsString('No video', $this->render([]));
    }

    public function test_plain_embed_unchanged_without_poster_or_qr(): void
    {
        $html = $this->render(['videoUrl' => 'https://youtu.be/abc12345']);
        $this->assertStringContainsString('youtube-nocookie.com/embed/abc12345', $html);
        $this->assertStringNotContainsString('autoplay=1', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_qr_overlay_renders_scannable_svg(): void
    {
        $html = $this->render(['videoUrl' => 'https://youtu.be/abc12345', 'showQr' => true]);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('pointer-events:none', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/abc12345', $html);
    }

    public function test_poster_renders_cover_with_click_to_play(): void
    {
        $html = $this->render([
            'videoUrl' => 'https://youtu.be/abc12345',
            'posterSrc' => '/api/v1/sites/s/assets/a/serve',
            'showQr' => true,
        ]);
        $this->assertStringContainsString('Video cover', $html);
        $this->assertStringContainsString('onclick=', $html);
        $this->assertStringContainsString('autoplay=1', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('aria-label="Play video"', $html);
    }

    public function test_direct_file_poster_swaps_to_video_element(): void
    {
        $html = $this->render([
            'videoUrl' => 'https://cdn.example.com/movie.mp4',
            'posterSrc' => 'https://cdn.example.com/cover.jpg',
        ]);
        $this->assertStringContainsString('createElement(&#039;video&#039;)', $html);
        $this->assertStringContainsString('movie.mp4', $html);
    }
}
