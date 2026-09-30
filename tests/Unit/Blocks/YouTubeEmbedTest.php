<?php

namespace Tests\Unit\Blocks;

use App\Support\Blocks\YouTubeEmbed;
use Tests\TestCase;

class YouTubeEmbedTest extends TestCase
{
    public function test_single_video_links(): void
    {
        foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ?si=x', 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://m.youtube.com/watch?feature=share&v=dQw4w9WgXcQ'] as $u) {
            $this->assertSame(['video' => 'dQw4w9WgXcQ', 'list' => null], YouTubeEmbed::parse($u), $u);
        }
    }

    public function test_playlists(): void
    {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/videoseries?playsinline=1&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG',
            YouTubeEmbed::embedUrl('https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG'),
        );
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?playsinline=1&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG&autoplay=1&mute=1',
            YouTubeEmbed::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG&index=2', true, true),
        );
    }

    public function test_other_hosts_are_not_youtube(): void
    {
        $this->assertNull(YouTubeEmbed::parse('https://evil.example/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(YouTubeEmbed::parse('https://example.com/video.mp4'));
    }

    public function test_video_block_renders_playlist_and_small_size(): void
    {
        $html = view('blocks.video', ['data' => ['url' => 'https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG', 'size' => 'small']])->render();
        $this->assertStringContainsString('embed/videoseries?playsinline=1&amp;list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG', $html);
        $this->assertStringContainsString('max-width:320px', $html);
    }
}
