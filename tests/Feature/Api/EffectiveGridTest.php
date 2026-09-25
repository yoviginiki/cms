<?php

namespace Tests\Feature\Api;

use App\Domain\Grid\Services\GridResolver;
use App\Models\Category;
use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * The "Grid" labels in Pages/Posts, the Grids page "Used by" and the site
 * default selector — all driven by EffectiveGridResolver, the same rule the
 * publisher uses to decide whether a grid wraps the content.
 */
class EffectiveGridTest extends TestCase
{
    private Site $site;
    private Grid $fullWidth;
    private Grid $landing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->fullWidth = $this->makeGrid('Full Width');
        $this->landing = $this->makeGrid('Landing');
        GridAssignment::create([
            'site_id' => $this->site->id, 'grid_id' => $this->fullWidth->id,
            'assignable_type' => 'default', 'assignable_id' => null, 'priority' => 9999,
        ]);
    }

    private function makeGrid(string $name): Grid
    {
        return Grid::create([
            'site_id' => $this->site->id, 'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(),
            'col_tracks' => '1fr', 'row_tracks' => 'auto', 'areas' => '"main"', 'is_preset' => false,
        ]);
    }

    private function listed(string $type): array
    {
        return collect($this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/{$type}?per_page=50", $this->apiHeaders())
            ->assertOk()->json('data'))->keyBy('id')->all();
    }

    public function test_pages_list_shows_the_grid_each_page_publishes_with_and_why(): void
    {
        $inherits = Page::factory()->create(['site_id' => $this->site->id]);
        $own = Page::factory()->create(['site_id' => $this->site->id, 'grid_id' => $this->landing->id]);
        $raw = Page::factory()->create(['site_id' => $this->site->id, 'raw_html' => '<html></html>']);

        $pages = $this->listed('pages');

        $this->assertSame('Full Width', $pages[$inherits->id]['effective_grid']['grid']['name']);
        $this->assertSame('default', $pages[$inherits->id]['effective_grid']['source']);
        $this->assertSame('Landing', $pages[$own->id]['effective_grid']['grid']['name']);
        $this->assertSame('override', $pages[$own->id]['effective_grid']['source']);
        $this->assertNull($pages[$raw->id]['effective_grid']['grid']);
        $this->assertSame('raw_html', $pages[$raw->id]['effective_grid']['reason']);
    }

    public function test_category_grid_applies_to_its_posts_and_builder_posts_skip_the_grid(): void
    {
        $category = Category::factory()->create(['site_id' => $this->site->id, 'grid_id' => $this->landing->id]);
        $simple = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => $category->id, 'editor_mode' => 'simple']);
        $builder = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => $category->id, 'editor_mode' => 'block']);

        $posts = $this->listed('posts');

        $this->assertSame('Landing', $posts[$simple->id]['effective_grid']['grid']['name']);
        $this->assertSame('category', $posts[$simple->id]['effective_grid']['source']);
        $this->assertSame('post_builder', $posts[$builder->id]['effective_grid']['reason']);

        // The publisher's resolver honours the category grid too (it used to ignore it).
        $this->assertSame($this->landing->id, app(GridResolver::class)->resolve($simple, $this->site)?->id);
    }

    public function test_usage_counts_what_publishes_with_each_grid(): void
    {
        Page::factory()->count(2)->create(['site_id' => $this->site->id]);
        $landingPage = Page::factory()->create(['site_id' => $this->site->id, 'grid_id' => $this->landing->id, 'title' => 'Launch']);

        $usage = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/grids/usage", $this->apiHeaders())
            ->assertOk()->json('data');

        $this->assertTrue($usage[$this->fullWidth->id]['is_default']);
        $this->assertCount(2, $usage[$this->fullWidth->id]['pages']);
        $this->assertSame([['id' => $landingPage->id, 'title' => 'Launch']], $usage[$this->landing->id]['pages']);
    }

    public function test_site_default_grid_can_be_changed_and_cleared(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/grids/default", ['grid_id' => $this->landing->id], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath("data.{$this->landing->id}.is_default", true);
        $this->assertSame('Landing', $this->listed('pages')[$page->id]['effective_grid']['grid']['name']);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/grids/default", ['grid_id' => null], $this->apiHeaders())
            ->assertOk();
        $this->assertSame('none', $this->listed('pages')[$page->id]['effective_grid']['source']);
    }

    public function test_default_grid_must_belong_to_the_site(): void
    {
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreign = Grid::create([
            'site_id' => $other->id, 'name' => 'Foreign', 'slug' => 'foreign-' . uniqid(),
            'col_tracks' => '1fr', 'row_tracks' => 'auto', 'areas' => '"main"', 'is_preset' => false,
        ]);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/grids/default", ['grid_id' => $foreign->id], $this->apiHeaders())
            ->assertStatus(422)->assertJsonValidationErrors(['grid_id']);
    }

    public function test_unified_sites_put_builder_posts_in_the_grid_legacy_sites_do_not(): void
    {
        $builder = Post::factory()->create(['site_id' => $this->site->id, 'editor_mode' => 'block']);
        $this->assertSame('post_builder', $this->listed('posts')[$builder->id]['effective_grid']['reason']);

        $this->site->update(['settings' => ['post_grid' => 'unified']]);
        $unified = $this->listed('posts')[$builder->id]['effective_grid'];
        $this->assertSame('Full Width', $unified['grid']['name']);
        $this->assertSame('default', $unified['source']);
    }

    public function test_new_sites_are_created_unified(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/sites', ['name' => 'Fresh Site'], $this->apiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.settings.post_grid', 'unified');
    }
}
