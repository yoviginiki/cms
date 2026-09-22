<?php

namespace Tests\Feature\Sites;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Site;
use App\Models\User;
use App\Services\BackupExportService;
use Tests\TestCase;

/**
 * F19 (audit 2026-09-22) — export → restore into a clean site is a real
 * round trip for the declared scope: nested block trees, raw pages, canvas
 * mode, theme document, categories, menus, redirects, section templates;
 * secrets never leave; the built HTML of a restored page equals the source.
 */
class BackupRestoreTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => [
            'anthropic_api_key' => 'sk-MARKER-secret', 'custom_css' => 'body{margin:0}', 'homepage_type' => 'page',
        ]]);
        $theme = \App\Models\Theme::create([
            'site_id' => $this->site->id, 'name' => 'Custom', 'slug' => 'custom', 'version' => '1.0.0', 'config' => ['colors' => ['primary' => '#123456']],
            'manifest_json' => [], 'template_path' => 'themes/default', 'document' => ['$metadata' => ['name' => 'Custom'], 'primitive' => ['color' => ['x' => ['$type' => 'color', '$value' => '#123456']]]],
            'modes' => ['light'], 'schema_version' => '1.0.0',
        ]);
        $this->site->update(['active_theme_id' => $theme->id]);
    }

    private function tree(array $t): array
    {
        return array_map(function ($b) {
            unset($b['id']);
            $b['children'] = $this->tree($b['children'] ?? []);

            return $b;
        }, $t);
    }

    public function test_export_restore_round_trip_into_a_clean_site(): void
    {
        $svc = app(BackupExportService::class);
        $blocks = app(BlockService::class);

        $cat = Category::create(['site_id' => $this->site->id, 'name' => 'News', 'slug' => 'news', 'sort_order' => 0]);
        $child = Category::create(['site_id' => $this->site->id, 'name' => 'Local', 'slug' => 'local', 'parent_id' => $cat->id, 'sort_order' => 1]);

        $home = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'home', 'title' => 'Home']);
        $blocks->syncBlocks($home, [[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => ['anchor' => 'top'],
            'children' => [['type' => 'row', 'level' => 'row', 'order' => 0, 'data' => [], 'children' => [[
                'type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [],
                'children' => [['type' => 'heading', 'level' => 'module', 'order' => 0, 'data' => ['text' => 'Здравей', 'level' => 'h1']]],
            ]]]],
        ]]);
        $raw = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'raw', 'title' => 'Raw', 'raw_html' => '<main id="raw">verbatim</main>']);
        $canvas = Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'canvas', 'title' => 'Canvas', 'editor_mode' => 'canvas', 'seo_meta' => ['canvas' => ['width' => 900]]]);
        $sub = Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'sub', 'title' => 'Sub', 'parent_id' => $home->id]);
        $post = Post::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'first', 'title' => 'First', 'category_id' => $child->id]);
        $blocks->syncBlocks($post, [['type' => 'text', 'order' => 0, 'data' => ['content' => '<p>Post body</p>']]]);

        $menu = Menu::create(['site_id' => $this->site->id, 'name' => 'Main', 'slug' => 'main', 'location' => 'header']);
        $top = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Home', 'page_id' => $home->id, 'sort_order' => 0]);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $top->id, 'label' => 'Sub', 'page_id' => $sub->id, 'sort_order' => 0]);
        Redirect::create(['site_id' => $this->site->id, 'source_path' => '/old', 'target_url' => '/home', 'status_code' => 301]);
        Redirect::create(['site_id' => $this->site->id, 'source_path' => '/x/?', 'target_url' => '/y', 'status_code' => 302, 'is_regex' => true]);
        \App\Models\BlockTemplate::create(['site_id' => $this->site->id, 'name' => 'CTA', 'slug' => 'cta', 'category' => 'cta', 'blocks_data' => [['type' => 'text', 'data' => ['content' => 'x']]]]);

        $manifest = $svc->export($this->site->fresh());
        $json = json_encode($manifest);
        $this->assertStringNotContainsString('MARKER', $json);
        $this->assertSame('2.0.0', $manifest['schema_version']);
        $this->assertNotEmpty($manifest['scope']['excluded']);
        $this->assertSame('<main id="raw">verbatim</main>', collect($manifest['pages'])->firstWhere('slug', 'raw')['raw_html']);
        $this->assertSame('canvas', collect($manifest['pages'])->firstWhere('slug', 'canvas')['editor_mode']);
        $this->assertSame('home', collect($manifest['pages'])->firstWhere('slug', 'sub')['parent_slug']);
        $this->assertSame('#123456', $manifest['theme']['document']['primitive']['color']['x']['$value']);

        // Restore into a clean site (same tenant), from the JSON round trip.
        $target = Site::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $this->site->name]);
        $result = $svc->restore(json_decode($json, true), $target);
        $this->assertSame(['pages' => 4, 'posts' => 1, 'categories' => 2, 'menus' => 1, 'redirects' => 2, 'section_templates' => 1, 'theme' => true], $result);

        $target = $target->fresh();
        $this->assertSame('body{margin:0}', $target->settings['custom_css']);
        $this->assertArrayNotHasKey('anthropic_api_key', $target->settings);
        $this->assertSame('#123456', $target->theme->document['primitive']['color']['x']['$value']);

        $rHome = Page::where('site_id', $target->id)->where('slug', 'home')->firstOrFail();
        $this->assertSame($this->tree($blocks->getBlockTree($home)), $this->tree($blocks->getBlockTree($rHome)));
        $this->assertSame('published', $rHome->status);
        $this->assertSame($rHome->id, Page::where('site_id', $target->id)->where('slug', 'sub')->firstOrFail()->parent_id);
        $this->assertSame('<main id="raw">verbatim</main>', Page::where('site_id', $target->id)->where('slug', 'raw')->firstOrFail()->raw_html);
        $this->assertSame('canvas', Page::where('site_id', $target->id)->where('slug', 'canvas')->firstOrFail()->editor_mode);
        $rPost = Post::where('site_id', $target->id)->where('slug', 'first')->firstOrFail();
        $this->assertSame('local', $rPost->category->slug);
        $this->assertSame('news', $rPost->category->parent->slug);
        $this->assertSame($this->tree($blocks->getBlockTree($post)), $this->tree($blocks->getBlockTree($rPost)));

        $rMenu = Menu::where('site_id', $target->id)->firstOrFail();
        $this->assertCount(2, $rMenu->items);
        $rTop = $rMenu->items->firstWhere('label', 'Home');
        $this->assertSame($rHome->id, $rTop->page_id);
        $this->assertSame($rTop->id, $rMenu->items->firstWhere('label', 'Sub')->parent_id);
        $this->assertSame(2, Redirect::where('site_id', $target->id)->count());
        $this->assertTrue((bool) Redirect::where('site_id', $target->id)->where('source_path', '/x/?')->first()->is_regex);

        // Visual parity proxy: the restored page builds to the same HTML.
        $build = app(BuildPageService::class);
        $a = $build->build($home->fresh(), $this->site->fresh()->theme, $this->site->fresh());
        $b = $build->build($rHome, $target->theme, $target);
        // ids and id-derived scope hashes legitimately differ between the two sites
        $normalize = fn (string $h, Site $s) => str_replace($s->slug, 'SLUG', preg_replace(['/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/\b[0-9a-f]{8}\b/'], ['UUID', 'HASH'], $h));
        $na = $normalize($a, $this->site->fresh());
        $nb = $normalize($b, $target);
        if ($na !== $nb) {
            $i = 0;
            while ($i < min(strlen($na), strlen($nb)) && $na[$i] === $nb[$i]) { $i++; }
            $this->fail("HTML differs at offset {$i}:\nA: " . substr($na, $i, 400) . "\nB: " . substr($nb, $i, 400));
        }
    }

    public function test_restore_refuses_a_non_empty_target_and_secrets_and_is_atomic(): void
    {
        $svc = app(BackupExportService::class);
        Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'p']);
        $manifest = $svc->export($this->site->fresh());

        try {
            $svc->restore($manifest, $this->site);
            $this->fail('non-empty target must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('empty site', $e->getMessage());
        }

        $poisoned = $manifest;
        $poisoned['site']['settings']['openai_api_key'] = 'sk-x';
        $this->assertFalse($svc->validateForRestore($poisoned)['can_restore']);

        // A broken page entry rolls the whole restore back.
        $target = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $broken = $manifest;
        $broken['pages'][] = ['title' => 'Bad', 'slug' => 'bad', 'blocks' => [['type' => 'no-such-type', 'order' => 0, 'data' => []]]];
        try {
            $svc->restore($broken, $target);
            $this->fail('expected the restore to fail');
        } catch (\Throwable) {
        }
        $this->assertSame(0, Page::where('site_id', $target->id)->withTrashed()->count());
    }

    public function test_backup_endpoints_are_role_gated(): void
    {
        $editor = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($editor, 'sanctum')->getJson("/api/v1/sites/{$this->site->id}/backup", $this->apiHeaders())->assertForbidden();
        $this->actingAsAdmin()->getJson("/api/v1/sites/{$this->site->id}/backup", $this->apiHeaders())->assertOk();

        $target = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $manifest = app(BackupExportService::class)->export($this->site->fresh());
        $this->actingAsAdmin()->postJson("/api/v1/sites/{$target->id}/backup/restore", ['manifest' => $manifest], $this->apiHeaders())->assertForbidden();
        $this->actingAsOwner()->postJson("/api/v1/sites/{$target->id}/backup/restore", ['manifest' => $manifest], $this->apiHeaders())->assertOk();
    }
}
