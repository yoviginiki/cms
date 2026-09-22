<?php

namespace Tests\Feature\Api;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F21 (audit 2026-09-22) — the shared preview URL is the real route, the
 * token is bound to tenant/site/type/content, the public render runs in the
 * token's tenant context with NO editor listener, invalid/expired/cross-site
 * tokens are refused, the live-update listener checks origin+source and can
 * reach the reload branch, and repeated blocks get their own ids.
 */
class SharedPreviewTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id, 'title' => 'SHARED PREVIEW TITLE']);
    }

    public function test_token_url_opens_anonymously_in_a_fresh_context(): void
    {
        $res = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/page/{$this->page->id}/preview-token", [], $this->apiHeaders())
            ->assertOk();
        $url = $res->json('data.url');
        $this->assertStringContainsString('/api/v1/preview/', $url, 'URL must point at the real route');

        // Anonymous visitor, no tenant context at all.
        DB::unprepared("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'");
        $page = $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('SHARED PREVIEW TITLE', $page->getContent());
        $this->assertStringNotContainsString('cms-preview-update', $page->getContent(), 'shared preview is read-only: no editor listener');
        $this->assertStringNotContainsString('__SP_EDIT', $page->getContent());
    }

    public function test_invalid_cross_site_and_wrong_type_tokens_are_refused(): void
    {
        $this->get('/api/v1/preview/' . str_repeat('a', 64))->assertNotFound();
        $this->get('/api/v1/preview/short')->assertNotFound();

        // content that does not belong to the site in the URL → no token
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$other->id}/page/{$this->page->id}/preview-token", [], $this->apiHeaders())
            ->assertNotFound();
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/widget/{$this->page->id}/preview-token", [], $this->apiHeaders())
            ->assertStatus(422);

        // an editor of ANOTHER tenant cannot mint a token for this site
        $stranger = User::factory()->owner()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/sites/{$this->site->id}/page/{$this->page->id}/preview-token", [], $this->apiHeaders())
            ->assertNotFound();
    }

    public function test_editor_preview_listener_checks_origin_and_source_and_can_reload(): void
    {
        $html = $this->actingAsOwner()
            ->get("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/preview")
            ->assertOk()->getContent();

        $origin = rtrim((string) config('app.url'), '/');
        $this->assertStringContainsString('event.origin!==ORIGIN', $html);
        $this->assertStringContainsString(json_encode($origin), $html);
        $this->assertStringContainsString('event.source!==window.parent', $html);
        // reload is handled BEFORE the update-type guard (was unreachable before)
        $this->assertLessThan(strpos($html, 'd.type!=="cms-preview-update"'), strpos($html, 'cms-preview-reload'));
    }

    public function test_repeated_blocks_get_their_own_ids(): void
    {
        app(BlockService::class)->syncBlocks($this->page, [
            ['type' => 'text', 'order' => 0, 'data' => ['content' => '<p>first</p>']],
            ['type' => 'text', 'order' => 1, 'data' => ['content' => '<p>second</p>']],
        ]);
        $ids = $this->page->blocks()->whereNull('parent_block_id')->orderBy('order')->pluck('id')->all();
        $html = $this->actingAsOwner()
            ->get("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/preview")
            ->assertOk()->getContent();

        $first = strpos($html, 'data-block-id="' . $ids[0] . '"');
        $second = strpos($html, 'data-block-id="' . $ids[1] . '"');
        $this->assertNotFalse($first, 'first text block not addressed');
        $this->assertNotFalse($second, 'second text block not addressed');
        $this->assertLessThan($second, $first);
        $this->assertSame(1, substr_count($html, 'data-block-id="' . $ids[0] . '"'));
    }
}
