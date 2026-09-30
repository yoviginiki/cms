<?php

namespace Tests\Feature\Publishing;

use App\Domain\Grid\Services\EffectiveGridResolver;
use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Models\Category;
use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\GridPosition;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * The one layout model: the grid owns header/nav/footer (pages, posts,
 * archives); a menu is never an implicit footer; a grid chosen on a post
 * always applies; the category grid is the post default.
 */
class OneLayoutModelTest extends TestCase
{
    private Site $site;
    private Grid $full;
    private Grid $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->full = $this->grid('Full Width', ['header' => ['menu', ['location' => 'header']], 'main' => ['canvas', []], 'footer' => ['menu', []]]);
        $this->other = $this->grid('Other', ['main' => ['canvas', []]]);
        GridAssignment::create(['site_id' => $this->site->id, 'grid_id' => $this->full->id, 'assignable_type' => 'default', 'assignable_id' => null, 'priority' => 9999]);
        foreach (['header', 'footer'] as $loc) {
            $m = Menu::create(['site_id' => $this->site->id, 'name' => $loc, 'slug' => $loc . '-menu', 'location' => $loc]);
            \App\Models\MenuItem::create(['menu_id' => $m->id, 'label' => 'Home', 'url' => '/', 'sort_order' => 0]);
        }
    }

    private function grid(string $name, array $areas): Grid
    {
        $g = Grid::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(),
            'col_tracks' => '1fr', 'row_tracks' => 'auto', 'areas' => '"' . implode('" "', array_keys($areas)) . '"', 'is_preset' => false]);
        $i = 0;
        foreach ($areas as $area => [$type, $config]) {
            GridPosition::create(['grid_id' => $g->id, 'area_name' => $area, 'label' => $area, 'type' => $type, 'config_json' => $config, 'scope' => 'page', 'mobile_order' => ++$i]);
        }

        return $g;
    }

    private function unify(): void
    {
        $this->site->update(['settings' => array_merge($this->site->settings ?? [], ['post_grid' => 'unified'])]);
        $this->site->refresh();
    }

    public function test_a_grid_chosen_on_a_post_applies_even_on_an_older_site(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id, 'editor_mode' => 'block', 'status' => 'published', 'published_at' => now()]);
        $r = app(EffectiveGridResolver::class);
        $this->assertSame('skipped', $r->forContent($post, $this->site)['source'], 'older site, no choice: legacy rule');

        $post->update(['grid_id' => $this->other->id]);
        $res = $r->forContent($post->fresh(), $this->site);
        $this->assertSame($this->other->id, $res['grid']['id']);
        $this->assertSame('override', $res['source']);
    }

    public function test_category_grid_is_the_post_default_and_the_post_can_override_it(): void
    {
        $this->unify();
        $cat = Category::factory()->create(['site_id' => $this->site->id, 'grid_id' => $this->other->id]);
        $post = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => $cat->id, 'editor_mode' => 'block']);
        $r = app(EffectiveGridResolver::class);
        $this->assertSame(['category', $this->other->id], [$r->forContent($post, $this->site)['source'], $r->forContent($post, $this->site)['grid']['id']]);

        $post->update(['grid_id' => $this->full->id]);
        $this->assertSame($this->full->id, $r->forContent($post->fresh(), $this->site)['grid']['id']);
    }

    public function test_post_grid_is_saved_and_reported_by_the_api(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id]);
        $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}", ['grid_id' => $this->other->id], $this->apiHeaders())->assertOk();
        $this->assertSame($this->other->id, $post->fresh()->grid_id);
        $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}/resolved-grid", $this->apiHeaders())
            ->assertOk()->assertJsonPath('data.grid.id', $this->other->id)->assertJsonPath('data.source', 'override');
    }

    public function test_unified_archives_wear_the_grid_chrome_not_an_implicit_footer_menu(): void
    {
        $this->unify();
        $vars = app(ArchiveBuildService::class)->getArchiveVars($this->site);
        $this->assertStringContainsString('pos-header', $vars['navigation']);
        // footer area is a menu placed there explicitly → the footer menu, from the grid
        $this->assertStringContainsString('pos-footer', $vars['footerNavigation']);

        GridPosition::where('grid_id', $this->full->id)->where('area_name', 'footer')->delete();
        $vars = app(ArchiveBuildService::class)->getArchiveVars($this->site->fresh());
        $this->assertSame('', $vars['footerNavigation'], 'no footer area → no footer (the footer menu is not a footer by itself)');
    }

    public function test_the_one_model_switch_is_a_site_setting(): void
    {
        $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}", ['settings' => ['post_grid' => 'unified']], $this->apiHeaders())->assertOk();
        $this->assertTrue(EffectiveGridResolver::postsUseGrid($this->site->fresh()));
        $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}", ['settings' => ['post_grid' => 'bogus']], $this->apiHeaders())->assertStatus(422);
    }
}
