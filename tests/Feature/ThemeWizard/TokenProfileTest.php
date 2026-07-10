<?php

namespace Tests\Feature\ThemeWizard;

use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Services\AI\SchemaRepairLoop;
use App\Services\ThemeWizard\FontAllowlist;
use App\Services\ThemeWizard\TokenProfileCompiler;
use App\Services\ThemeWizard\TokenProfileSchema;
use App\Services\ThemeWizard\TokenProfileValidator;
use App\Models\Site;
use App\Models\Theme;
use Tests\TestCase;

class TokenProfileTest extends TestCase
{
    private function validProfile(array $over = []): array
    {
        return array_replace_recursive([
            'name' => 'Coastal Studio',
            'design_read' => 'Airy, warm, editorial — sand paper, a single teal mark, generous space.',
            'palette' => [
                'brand' => '#0E7C86', 'accent' => '#C4622D',
                'background' => '#FBF8F2', 'surface' => '#F3EEE4',
                'text' => '#3A3730', 'heading' => '#1C1A15', 'muted' => '#8A8474', 'border' => '#E4DDCF',
            ],
            'typography' => [
                'display_character' => 'high-contrast editorial serif',
                'body_character' => 'warm humanist sans',
                'scale' => 'dramatic', 'heading_weight' => 500,
            ],
            'spacing' => 'airy', 'radius' => 'soft', 'shadow' => 'subtle', 'layout' => 'magazine',
        ], $over);
    }

    // ── validator ──

    public function test_valid_profile_passes(): void
    {
        $this->assertSame([], (new TokenProfileValidator())->validate($this->validProfile()));
    }

    public function test_low_contrast_body_text_is_rejected(): void
    {
        $errors = (new TokenProfileValidator())->validate($this->validProfile([
            'palette' => ['text' => '#F0EEE8'], // near-white on cream
        ]));
        $this->assertNotEmpty($errors);
        $this->assertStringContainsStringIgnoringCase('contrast', implode(' ', $errors));
    }

    public function test_accent_identical_to_brand_is_rejected(): void
    {
        $errors = (new TokenProfileValidator())->validate($this->validProfile([
            'palette' => ['accent' => '#0E7C86'], // == brand
        ]));
        $this->assertNotEmpty($errors);
        $this->assertStringContainsStringIgnoringCase('accent', implode(' ', $errors));
    }

    public function test_bad_enum_and_hex_are_rejected(): void
    {
        $v = new TokenProfileValidator();
        $this->assertNotEmpty($v->validate($this->validProfile(['layout' => 'flipbook'])));
        $this->assertNotEmpty($v->validate($this->validProfile(['radius' => 'squircle'])));
        $this->assertNotEmpty($v->validate($this->validProfile(['palette' => ['brand' => 'teal']])));
    }

    // ── font allowlist ──

    public function test_font_suggestions_are_always_allowlisted(): void
    {
        foreach (['high-contrast serif', 'geometric sans', 'condensed grotesque', 'nonsense words xyz'] as $c) {
            $this->assertTrue(FontAllowlist::isAllowed(FontAllowlist::suggest('display', $c)));
            $this->assertTrue(FontAllowlist::isAllowed(FontAllowlist::suggest('body', $c)));
        }
    }

    public function test_font_character_matching(): void
    {
        $this->assertSame('Space Grotesk', FontAllowlist::suggest('display', 'geometric techy sans'));
        $this->assertSame('Nunito Sans', FontAllowlist::suggest('body', 'warm rounded humanist sans'));
    }

    // ── compiler → resolvable theme ──

    public function test_compiled_profile_resolves_to_published_css(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $compiled = (new TokenProfileCompiler())->compile($this->validProfile());
        $this->assertArrayHasKey('document', $compiled);
        $this->assertSame('magazine', $compiled['document']['layout']['style']);

        $theme = Theme::create([
            'site_id' => $site->id, 'name' => $compiled['name'], 'slug' => $compiled['slug'],
            'version' => '1.0.0', 'config' => [], 'manifest_json' => [], 'template_path' => '',
            'document' => $compiled['document'], 'modes' => ['light'], 'schema_version' => '1.0.0',
        ]);

        $css = app(DesignTokenGenerator::class)->generateForTheme($theme, $site);
        // palette reaches published CSS (both semantic + legacy alias)
        $this->assertStringContainsString('#0E7C86', $css);
        $this->assertStringContainsString('--color-primary', $css);
        // substituted open fonts, not a copied reference font
        $this->assertStringContainsStringIgnoringCase('Fraunces', $css);   // display: editorial serif
        $this->assertStringContainsString('@import', $css);                 // Google Font import emitted
        // soft radius mapped through
        $this->assertMatchesRegularExpression('/--border-radius-md:\s*8px/', $css);
    }

    public function test_rounded_radius_and_no_shadow_map_through(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $compiled = (new TokenProfileCompiler())->compile($this->validProfile(['radius' => 'rounded', 'shadow' => 'none']));
        $theme = Theme::create([
            'site_id' => $site->id, 'name' => $compiled['name'], 'slug' => $compiled['slug'],
            'version' => '1.0.0', 'config' => [], 'manifest_json' => [], 'template_path' => '',
            'document' => $compiled['document'], 'modes' => ['light'], 'schema_version' => '1.0.0',
        ]);
        $css = app(DesignTokenGenerator::class)->generateForTheme($theme, $site);
        $this->assertMatchesRegularExpression('/--border-radius-lg:\s*20px/', $css);
        $this->assertMatchesRegularExpression('/--shadow-md:\s*none/', $css);
    }

    // ── schema repair loop ──

    public function test_repair_loop_returns_on_valid_output(): void
    {
        $loop = new SchemaRepairLoop();
        $out = $loop->run(
            fn ($msgs) => ['text' => '{"ok":true}', 'usage' => ['input' => 1, 'output' => 1]],
            fn ($decoded) => ($decoded['ok'] ?? false) ? [] : ['not ok'],
            [['role' => 'user', 'content' => 'go']],
        );
        $this->assertTrue($out['data']['ok']);
        $this->assertCount(1, $out['usages']);
    }

    public function test_repair_loop_repairs_then_succeeds(): void
    {
        $calls = 0;
        $loop = new SchemaRepairLoop();
        $out = $loop->run(
            function ($msgs) use (&$calls) {
                $calls++;
                return ['text' => $calls === 1 ? '{"ok":false}' : '{"ok":true}', 'usage' => ['input' => 1, 'output' => 1]];
            },
            fn ($d) => ($d['ok'] ?? false) ? [] : ['ok must be true'],
            [['role' => 'user', 'content' => 'go']],
        );
        $this->assertTrue($out['data']['ok']);
        $this->assertSame(2, $calls);
        $this->assertCount(2, $out['usages']); // both attempts charged
    }

    public function test_repair_loop_throws_after_max_attempts(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SchemaRepairLoop())->run(
            fn ($msgs) => ['text' => 'not json', 'usage' => []],
            fn ($d) => ['always fails'],
            [['role' => 'user', 'content' => 'go']],
        );
    }

    public function test_schema_is_wellformed(): void
    {
        $s = TokenProfileSchema::schema();
        $this->assertSame('object', $s['type']);
        $this->assertContains('palette', $s['required']);
        $this->assertContains('magazine', $s['properties']['layout']['enum']);
    }
}
