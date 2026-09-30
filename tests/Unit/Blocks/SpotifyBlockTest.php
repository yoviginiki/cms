<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\SpotifyBlockDefinition;
use App\Support\Blocks\SpotifyEmbed;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SpotifyBlockTest extends TestCase
{
    public function test_share_link_uri_and_iframe_code_become_the_embed_url(): void
    {
        $this->assertSame(
            'https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M?utm_source=generator',
            SpotifyEmbed::embedUrl('https://open.spotify.com/intl-de/playlist/37i9dQZF1DXcBWIGoYBM5M?si=abc123'),
        );
        $this->assertSame(['type' => 'album', 'id' => '4aawyAB9vmqN3uQ7FjRGTy'], SpotifyEmbed::parse('spotify:album:4aawyAB9vmqN3uQ7FjRGTy'));
        $this->assertSame(
            'https://open.spotify.com/embed/track/11dFghVXANMlKmJXsNCbNl?utm_source=generator&theme=0',
            SpotifyEmbed::embedUrl('<iframe src="https://open.spotify.com/embed/track/11dFghVXANMlKmJXsNCbNl?utm_source=generator"></iframe>', true),
        );
    }

    public function test_non_spotify_input_is_rejected(): void
    {
        $this->assertNull(SpotifyEmbed::embedUrl('https://evil.example/playlist/37i9dQZF1DXcBWIGoYBM5M'));
        $this->assertNull(SpotifyEmbed::embedUrl('javascript:alert(1)'));
        $this->assertNull(SpotifyEmbed::embedUrl(''));
    }

    public function test_default_heights(): void
    {
        $this->assertSame(352, SpotifyEmbed::defaultHeight('spotify:playlist:37i9dQZF1DXcBWIGoYBM5M'));
        $this->assertSame(152, SpotifyEmbed::defaultHeight('spotify:track:11dFghVXANMlKmJXsNCbNl'));
        $this->assertSame(152, SpotifyEmbed::defaultHeight('spotify:playlist:37i9dQZF1DXcBWIGoYBM5M', 'compact'));
    }

    public function test_validation(): void
    {
        $rules = (new SpotifyBlockDefinition())->validationRules();
        $this->assertTrue(Validator::make(['url' => 'https://open.spotify.com/playlist/x', 'theme' => 'dark', 'size' => 'compact'], $rules)->passes());
        $this->assertTrue(Validator::make(['theme' => 'neon'], $rules)->fails());
    }

    public function test_block_renders_the_player_iframe(): void
    {
        $html = view('blocks.spotify', ['data' => ['url' => 'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M?si=x']])->render();
        $this->assertStringContainsString('src="https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M?utm_source=generator"', $html);
        $this->assertStringContainsString('--sp-h:352px', $html);
        $this->assertStringNotContainsString('<iframe', view('blocks.spotify', ['data' => ['url' => 'https://evil.example/x']])->render());
    }
}
