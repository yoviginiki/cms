<?php

namespace Tests\Feature\ThemeWizard;

use App\Services\ThemeWizard\TokenProfileCompiler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerbatimFontTest extends TestCase
{
    private function profile(array $typography): array
    {
        return [
            'name' => 'Test',
            'design_read' => 'x',
            'palette' => [
                'brand' => '#3b5a4a', 'accent' => '#a85d47', 'background' => '#f5f2ea',
                'surface' => '#ffffff', 'text' => '#22271f', 'heading' => '#171c15',
                'muted' => '#6b7265', 'border' => '#ddd8cc',
            ],
            'typography' => array_merge([
                'display_character' => 'high-contrast elegant serif',
                'body_character' => 'neutral geometric sans',
                'scale' => 'balanced',
                'heading_weight' => 600,
            ], $typography),
            'spacing' => 'balanced',
            'radius' => 'soft',
            'shadow' => 'subtle',
            'layout' => 'standard',
        ];
    }

    private function fontOf(array $compiled, string $role): string
    {
        return $compiled['document']['wizard'][$role === 'font-heading' ? 'display_font' : 'body_font'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_available_google_font_is_used_verbatim(): void
    {
        Http::fake(['fonts.googleapis.com/*' => Http::response('@font-face{}', 200)]);

        $compiled = app(TokenProfileCompiler::class)->compile($this->profile([
            'display_family' => 'Spectral',
            'body_family' => 'Manrope',
        ]));

        $this->assertSame('Spectral', $this->fontOf($compiled, 'font-heading'));
        $this->assertSame('Manrope', $this->fontOf($compiled, 'font-body'));
    }

    public function test_unavailable_family_falls_back_to_allowlist(): void
    {
        Http::fake(['fonts.googleapis.com/*' => Http::response('bad', 400)]);

        $compiled = app(TokenProfileCompiler::class)->compile($this->profile([
            'display_family' => 'Neue Haas Grotesk',
            'body_family' => 'Proprietary Sans',
        ]));

        // character-matched open substitutes, never the licensed names
        $this->assertNotSame('Neue Haas Grotesk', $this->fontOf($compiled, 'font-heading'));
        $this->assertNotSame('Proprietary Sans', $this->fontOf($compiled, 'font-body'));
        $this->assertTrue(\App\Services\ThemeWizard\FontAllowlist::isAllowed($this->fontOf($compiled, 'font-heading')));
    }

    public function test_no_family_means_no_network_and_classic_behavior(): void
    {
        Http::fake();

        $compiled = app(TokenProfileCompiler::class)->compile($this->profile([]));

        Http::assertNothingSent();
        $this->assertTrue(\App\Services\ThemeWizard\FontAllowlist::isAllowed($this->fontOf($compiled, 'font-heading')));
    }

    public function test_verbatim_serif_gets_serif_css_fallback(): void
    {
        Http::fake(['fonts.googleapis.com/*' => Http::response('@font-face{}', 200)]);

        $compiled = app(TokenProfileCompiler::class)->compile($this->profile([
            'display_family' => 'Spectral',
        ]));

        // Spectral is not a display font in the allowlist — the fallback must
        // come from the character phrase ("… serif") instead: Georgia, serif.
        $found = false;
        array_walk_recursive($compiled, function ($v) use (&$found) {
            if ($v === 'Georgia') { $found = true; }
        });
        $this->assertTrue($found, 'serif css fallback expected after a verbatim serif family');
    }
}
