<?php

namespace Tests\Feature\Sites;

use App\Domain\Sites\Services\AiSiteContentService;
use App\Domain\Sites\Services\StarterTemplateService;
use App\Models\Block;
use App\Models\Site;
use Tests\TestCase;

/**
 * Starter templates — the site-creation page scaffolder. Focus on the new
 * "Full Site" template that lays out the complete page set a user expects.
 */
class StarterTemplateTest extends TestCase
{
    private function service(): StarterTemplateService
    {
        return app(StarterTemplateService::class);
    }

    private function slugsFor(Site $site): array
    {
        return $site->pages()->orderBy('created_at')->pluck('slug')->all();
    }

    public function test_full_template_is_offered(): void
    {
        $full = collect($this->service()->getTemplates())->firstWhere('id', 'full');
        $this->assertNotNull($full);
        $this->assertSame(
            ['home', 'landing', 'catalog', 'portfolio', 'contact', 'blog', 'about', 'features'],
            $full['pages'],
        );
    }

    public function test_full_template_creates_the_complete_page_set(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service()->apply($site, 'full');

        $this->assertTrue($result['success']);
        $this->assertSame(8, $result['pages_created']);
        $this->assertEqualsCanonicalizing(
            ['home', 'landing', 'catalog', 'portfolio', 'contact', 'blog', 'about', 'features'],
            $this->slugsFor($site),
        );

        // homepage wired to the home page
        $home = $site->pages()->where('slug', 'home')->first();
        $this->assertSame($home->id, $site->fresh()->settings['homepage_id']);

        // blog page brings sample posts so the latestposts block has content
        $this->assertSame(3, $site->posts()->count());
    }

    public function test_full_template_pages_carry_their_signature_blocks(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service()->apply($site, 'full');

        $hasBlock = function (string $slug, string $type) use ($site): bool {
            $page = $site->pages()->where('slug', $slug)->firstOrFail();
            return Block::where('blockable_type', $page->getMorphClass())
                ->where('blockable_id', $page->id)->where('type', $type)->exists();
        };

        $this->assertTrue($hasBlock('features', 'feature-grid'));
        $this->assertTrue($hasBlock('catalog', 'catalog'));
        $this->assertTrue($hasBlock('portfolio', 'gallery'));
        $this->assertTrue($hasBlock('contact', 'contact-form'));
        $this->assertTrue($hasBlock('blog', 'latestposts'));
    }

    public function test_full_template_with_topic_uses_ai_copy_and_industry_images(): void
    {
        $feat = fn (string $t) => ['title' => $t, 'desc' => "About {$t}."];
        $this->mock(AiSiteContentService::class, function ($m) use ($feat) {
            $m->shouldReceive('generate')->once()->andReturn([
                '_images' => 'hvac,air',
                'home' => [
                    'heading' => '24/7 Heating & Cooling', 'subtext' => 'Fast, reliable HVAC service.', 'cta' => 'Book Now',
                    'testimonial' => ['quote' => 'They fixed our furnace in an hour on a freezing night.', 'author' => 'Dana P.'],
                    'stats' => [['value' => '5,000+', 'label' => 'Jobs completed'], ['value' => '4.9★', 'label' => 'Average rating'], ['value' => '24/7', 'label' => 'Emergency service']],
                ],
                'landing' => ['heading' => 'Comfort All Year', 'subtext' => 'x', 'cta' => 'Get a Quote', 'closing_heading' => 'Stay comfortable', 'features' => [$feat('AC Repair'), $feat('Furnace'), $feat('Ducts')]],
                'catalog' => ['heading' => 'Services', 'intro' => 'x', 'items' => [
                    ['title' => 'AC Install', 'subtitle' => 'From $—', 'desc' => 'x'],
                    ['title' => 'Furnace Service', 'subtitle' => 'From $—', 'desc' => 'y'],
                    ['title' => 'Maintenance Plan', 'subtitle' => 'From $—', 'desc' => 'z'],
                ]],
                'portfolio' => ['heading' => 'Our Work', 'intro' => 'Recent installs.'],
                'contact' => ['heading' => 'Contact Us', 'intro' => 'x'],
                'about' => ['heading' => 'About Our Company', 'paragraph1' => 'x', 'paragraph2' => 'y'],
                'features' => ['heading' => 'Why Choose Us', 'intro' => 'x', 'items' => array_map($feat, ['Licensed', 'Insured', 'Fast', 'Fair', 'Local', 'Guaranteed'])],
                'blog' => ['heading' => 'HVAC Tips', 'posts' => [
                    ['title' => 'Save on Heating This Winter', 'excerpt' => 'x', 'body' => ['Lower your thermostat at night.', 'Seal drafty windows and doors.']],
                    ['title' => 'When to Replace Your AC', 'excerpt' => 'y', 'body' => ['Units over 15 years cost more to run.']],
                    ['title' => 'Signs of a Failing Furnace', 'excerpt' => 'z', 'body' => ['Watch for uneven heating and odd noises.']],
                ]],
            ]);
            $m->shouldReceive('imageUrl')->andReturnUsing(fn ($kw, $lock) => "https://loremflickr.com/1200/800/{$kw}?lock={$lock}");
        });

        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service()->apply($site, 'full', 'HVAC company');

        $blocksOf = function (string $slug) use ($site) {
            $page = $site->pages()->where('slug', $slug)->firstOrFail();
            return Block::where('blockable_type', $page->getMorphClass())->where('blockable_id', $page->id)->get();
        };
        $hasText = fn ($blocks, string $needle) => $blocks->contains(fn ($b) => str_contains(json_encode($b->data), $needle));

        // industry-specific copy landed, plus AI social proof (stats + testimonial)
        $this->assertTrue($hasText($blocksOf('home'), 'Heating & Cooling'));
        $this->assertTrue($hasText($blocksOf('home'), 'Jobs completed'));
        $this->assertTrue($hasText($blocksOf('home'), 'fixed our furnace'));
        $this->assertTrue($hasText($blocksOf('about'), 'About Our Company'));
        $this->assertTrue($hasText($blocksOf('catalog'), 'AC Install'));

        // industry images on the portfolio gallery
        $gallery = $blocksOf('portfolio')->firstWhere('type', 'gallery');
        $this->assertNotEmpty($gallery->data['images']);
        $this->assertStringContainsString('loremflickr.com/1200/800/hvac,air', $gallery->data['images'][0]);

        // AI-written blog posts, with real body content + SEO meta
        $post = $site->posts()->where('title', 'Save on Heating This Winter')->first();
        $this->assertNotNull($post);
        $postBlocks = Block::where('blockable_type', $post->getMorphClass())->where('blockable_id', $post->id)->get();
        $this->assertTrue($postBlocks->contains(fn ($b) => str_contains(json_encode($b->data), 'Seal drafty windows')));
        $this->assertNotEmpty($post->seo_meta['description'] ?? '');

        // pages carry SEO meta descriptions from the AI copy
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $this->assertStringContainsString('Fast, reliable HVAC service', $home->seo_meta['description'] ?? '');
    }

    public function test_apply_is_idempotent(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service()->apply($site, 'full');
        $again = $this->service()->apply($site, 'full');

        $this->assertSame(0, $again['pages_created']);
        $this->assertSame(8, $again['pages_skipped']);
        $this->assertCount(8, $this->slugsFor($site));
    }
}
