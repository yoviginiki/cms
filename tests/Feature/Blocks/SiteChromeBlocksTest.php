<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Site;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Grid areas as blocks — stage 2: the site-chrome blocks that replace the
 * old grid widgets (social links, logo/name, copyright, back to top).
 */
class SiteChromeBlocksTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Acme Studio',
            'settings' => ['logo_url' => '/logo.png', 'tagline' => 'We make things'],
        ]);
    }

    private function render(string $type, array $data): string
    {
        return app(BuildPageService::class)->renderBlock(new Block(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => $type, 'data' => $data]), $this->site);
    }

    private function rules(string $type): array
    {
        return app(BlockRegistry::class)->get($type)->validationRules();
    }

    public function test_all_four_are_registered(): void
    {
        foreach (['social-links', 'site-identity', 'copyright', 'back-to-top'] as $type) {
            $this->assertNotNull(app(BlockRegistry::class)->get($type), $type);
        }
    }

    public function test_social_links_render_icons_normalise_urls_and_skip_bad_ones(): void
    {
        $html = $this->render('social-links', ['style' => 'circle', 'links' => [
            ['network' => 'facebook', 'url' => 'facebook.com/acme'],
            ['network' => 'email', 'url' => 'hi@acme.test'],
            ['network' => 'phone', 'url' => '+359 88 123 4567'],
            ['network' => 'website', 'url' => 'javascript:alert(1)'],
            ['network' => 'instagram', 'url' => ''],
        ]]);

        $this->assertStringContainsString('href="https://facebook.com/acme"', $html);
        $this->assertStringContainsString('rel="noopener me"', $html);
        $this->assertStringContainsString('href="mailto:hi@acme.test"', $html);
        $this->assertStringContainsString('href="tel:+359881234567"', $html);
        $this->assertStringContainsString('aria-label="Facebook"', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame(3, substr_count($html, '<li'));
    }

    public function test_social_links_validation(): void
    {
        $rules = $this->rules('social-links');
        $this->assertTrue(Validator::make(['links' => [['network' => 'x', 'url' => 'https://x.com/a']]], $rules)->passes());
        $this->assertFalse(Validator::make(['links' => [['network' => 'myspace', 'url' => 'https://a']]], $rules)->passes());
        $this->assertFalse(Validator::make(['style' => 'huge'], $rules)->passes());
    }

    public function test_site_identity_uses_branding_and_links_home(): void
    {
        $html = $this->render('site-identity', ['show' => 'both', 'showTagline' => true]);
        $this->assertStringContainsString('src="/logo.png"', $html);
        $this->assertStringContainsString('Acme Studio', $html);
        $this->assertStringContainsString('We make things', $html);
        $this->assertStringContainsString('href="/"', $html);

        // auto without a logo falls back to the name
        $this->site->update(['settings' => []]);
        $html = $this->render('site-identity', ['show' => 'auto']);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Acme Studio', $html);
    }

    public function test_copyright_fills_tokens_and_escapes(): void
    {
        $year = date('Y');
        $this->assertStringContainsString("© 2019–{$year} Acme Studio", $this->render('copyright', ['text' => '© {year} {site}', 'startYear' => 2019]));
        $this->assertStringContainsString("© {$year} Acme Studio. All rights reserved.", $this->render('copyright', []));
        $this->assertStringNotContainsString('<script>', $this->render('copyright', ['text' => '<script>x</script> {year}']));
    }

    public function test_back_to_top_renders_an_accessible_control(): void
    {
        $html = $this->render('back-to-top', ['style' => 'icon', 'label' => 'Up']);
        $this->assertStringContainsString('aria-label="Up"', $html);
        $this->assertStringContainsString('window.scrollTo', $html);
        $this->assertFalse(Validator::make(['style' => 'rocket'], $this->rules('back-to-top'))->passes());
    }

    public function test_contact_links_pages_emails_phones_and_own_icons(): void
    {
        $html = $this->render('social-links', ['links' => [
            ['network' => 'contact', 'url' => '/kontakti/'],
            ['network' => 'contact', 'url' => 'hi@acme.test'],
            ['network' => 'contact', 'url' => '+359 88 111 2233'],
            ['network' => 'facebook', 'url' => 'https://facebook.com/acme', 'icon' => '/uploads/fb.png'],
        ]]);

        $this->assertStringContainsString('href="/kontakti/"', $html);
        $this->assertStringContainsString('href="mailto:hi@acme.test"', $html);
        $this->assertStringContainsString('href="tel:+359881112233"', $html);
        $this->assertStringContainsString('<img src="/uploads/fb.png"', $html);
        $this->assertSame(3, substr_count($html, '<svg'));
    }

    public function test_links_being_filled_in_validate_and_bad_icons_do_not(): void
    {
        $rules = $this->rules('social-links');
        $this->assertTrue(Validator::make(['links' => [['network' => 'facebook', 'url' => '']]], $rules)->passes());
        $this->assertFalse(Validator::make(['links' => [['network' => 'facebook', 'url' => 'x', 'icon' => 'javascript:alert(1)']]], $rules)->passes());
    }

    public function test_exact_icon_size_and_gap_override_the_preset(): void
    {
        $html = $this->render('social-links', ['size' => 'sm', 'iconSize' => 48, 'gap' => 24, 'links' => [
            ['network' => 'facebook', 'url' => 'https://facebook.com/a', 'icon' => '/img/fb.png'],
        ]]);
        $this->assertStringContainsString('width="48" height="48"', $html);
        $this->assertStringContainsString('gap:24px', $html);

        $html = $this->render('social-links', ['size' => 'lg', 'links' => [['network' => 'facebook', 'url' => 'https://facebook.com/a', 'icon' => '/img/fb.png']]]);
        $this->assertStringContainsString('width="26" height="26"', $html);

        $rules = $this->rules('social-links');
        $this->assertTrue(Validator::make(['iconSize' => 48, 'gap' => 24], $rules)->passes());
        $this->assertTrue(Validator::make(['iconSize' => null], $rules)->passes());
        $this->assertFalse(Validator::make(['iconSize' => 5000], $rules)->passes());
        $many = array_fill(0, 30, ['network' => 'website', 'url' => 'https://a.bg']);
        $this->assertTrue(Validator::make(['links' => $many], $rules)->passes());
    }

    public function test_title_can_be_placed_styled_and_hidden(): void
    {
        $links = [['network' => 'facebook', 'url' => 'https://facebook.com/a']];
        $title = ['show' => true, 'text' => 'Follow Us', 'tag' => 'h4', 'position' => 'below', 'fontSize' => 22,
            'fontFamily' => 'var(--font-heading)', 'fontWeight' => '700', 'color' => '#ff0000', 'padding' => ['top' => 4, 'bottom' => 10]];

        $html = $this->render('social-links', ['align' => 'center', 'links' => $links, 'title' => $title]);
        $this->assertMatchesRegularExpression('#</ul>\s*<h4 class="social-links-title" style="text-align:center;margin:0;font-size:22px;font-family:var\(--font-heading\);font-weight:700;color:\#ff0000;padding:4px 0px 10px 0px;">Follow Us</h4>#', $html);

        $html = $this->render('social-links', ['links' => $links, 'title' => ['position' => 'before'] + $title]);
        $this->assertLessThan(strpos($html, '<ul'), strpos($html, '<h4'));
        $this->assertStringContainsString('flex-wrap:wrap;align-items:center', $html);

        $this->assertStringNotContainsString('Follow Us', $this->render('social-links', ['links' => $links, 'title' => ['show' => false] + $title]));
        // No links → nothing, not an orphan title
        $this->assertStringNotContainsString('Follow Us', $this->render('social-links', ['links' => [], 'title' => $title]));

        $rules = $this->rules('social-links');
        $this->assertTrue(Validator::make(['title' => $title], $rules)->passes());
        $this->assertFalse(Validator::make(['title' => ['position' => 'sideways']], $rules)->passes());
        $this->assertFalse(Validator::make(['title' => ['fontFamily' => 'x;background:url(evil)']], $rules)->passes());
        $this->assertFalse(Validator::make(['title' => ['tag' => 'script']], $rules)->passes());
    }

    public function test_icon_background_can_be_turned_off_and_shadow_added(): void
    {
        $links = [['network' => 'facebook', 'url' => 'https://facebook.com/a', 'icon' => '/img/fb.png']];

        $html = $this->render('social-links', ['style' => 'circle', 'iconBg' => 'none', 'shadow' => 'md', 'links' => $links]);
        $this->assertStringContainsString('background:transparent;', $html);
        $this->assertStringContainsString('filter:drop-shadow(', $html);
        $this->assertStringNotContainsString('box-shadow', $html);

        $html = $this->render('social-links', ['style' => 'circle', 'iconBg' => '#ffffff', 'shadow' => 'lg', 'links' => $links]);
        $this->assertStringContainsString('background:#ffffff;box-shadow:0 10px 24px', $html);
        $this->assertStringNotContainsString('drop-shadow', $html);

        $this->assertStringContainsString('background:var(--color-bg-alt', $this->render('social-links', ['style' => 'circle', 'links' => $links]));

        $rules = $this->rules('social-links');
        $this->assertTrue(Validator::make(['iconBg' => 'none', 'shadow' => 'sm'], $rules)->passes());
        $this->assertFalse(Validator::make(['iconBg' => 'red;x:y'], $rules)->passes());
        $this->assertFalse(Validator::make(['shadow' => 'huge'], $rules)->passes());
    }
}
