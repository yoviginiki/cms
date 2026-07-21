<?php

namespace Tests\Feature\Sites;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Site;
use Database\Seeders\DocsSiteSeeder;
use Tests\TestCase;

/**
 * S2 — dogfooded docs: the seeder creates a `docs` site whose pages are
 * ordinary CMS content, idempotently, and the pages render to real HTML
 * through the normal build path.
 */
class DocsSiteSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
    }

    public function test_seeds_docs_site_with_published_guides(): void
    {
        $this->seed(DocsSiteSeeder::class);

        $site = Site::where('slug', 'docs')->first();
        $this->assertNotNull($site);
        $this->assertSame($this->tenant->id, $site->tenant_id);

        $slugs = $site->pages()->pluck('slug')->sort()->values()->all();
        $this->assertSame(['collections', 'collections-v3', 'forms', 'home', 'importing-data', 'queries', 'wizards'], $slugs);
        $this->assertSame(0, $site->pages()->where('status', '!=', 'published')->count());

        // Homepage wired to the docs index
        $home = $site->pages()->where('slug', 'home')->first();
        $this->assertSame($home->id, $site->fresh()->settings['homepage_id']);

        // Every page carries real block content
        foreach ($site->pages as $page) {
            $this->assertGreaterThan(0, $page->blocks()->count(), "{$page->slug} has blocks");
        }
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seed(DocsSiteSeeder::class);
        $site = Site::where('slug', 'docs')->first();
        $firstPageCount = $site->pages()->count();
        $firstBlockCount = $site->pages->sum(fn ($p) => $p->blocks()->count());

        $this->seed(DocsSiteSeeder::class);

        $this->assertSame(1, Site::where('slug', 'docs')->count());
        $this->assertSame($firstPageCount, $site->pages()->count());
        $this->assertSame(
            $firstBlockCount,
            $site->fresh()->pages->sum(fn ($p) => $p->blocks()->count()),
            'block counts stable across re-seed',
        );
    }

    public function test_guides_render_to_html_with_honest_limits(): void
    {
        $this->seed(DocsSiteSeeder::class);
        $site = Site::where('slug', 'docs')->first();
        $builder = app(BuildPageService::class);

        $collections = $builder->build($site->pages()->where('slug', 'collections')->first(), null, $site);
        $this->assertStringContainsString('Publishing tiers', $collections);
        $this->assertStringContainsString('2,000 records', $collections);
        $this->assertStringContainsString('one hop', $collections);

        $import = $builder->build($site->pages()->where('slug', 'importing-data')->first(), null, $site);
        $this->assertStringContainsString('50 MB', $import);
        $this->assertStringContainsString('Update by key', $import);
        $this->assertStringContainsString('pipe-separated slugs', $import);

        $queries = $builder->build($site->pages()->where('slug', 'queries')->first(), null, $site);
        $this->assertStringContainsString('SQL constraints', $queries);
        $this->assertStringContainsString('3-second timeout', $queries);
        $this->assertStringContainsString('one hop', $queries);
    }
}
