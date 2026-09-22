<?php

namespace Tests\Feature\Security;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Site;
use App\Models\User;
use Tests\TestCase;

/**
 * F05 (audit 2026-09-22) — raw HTML/JS is a trusted surface: html-embed
 * blocks AND page.raw_html. An editor must not be able to create or change
 * executable HTML through ANY write path (blocks sync, raw_html, version
 * restore, inline edit), while still being able to edit the rest of a page
 * that already carries an admin-authored embed.
 */
class TrustedHtmlGateTest extends TestCase
{
    private Site $site;
    private Page $page;
    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->create(['site_id' => $this->site->id]);
        $this->editor = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
    }

    private function url(): string
    {
        return "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks";
    }

    private function heading(string $text = 'Hi', int $order = 0): array
    {
        return ['type' => 'heading', 'order' => $order, 'data' => ['text' => $text, 'level' => 'h1']];
    }

    private function embed(string $html, int $order = 1): array
    {
        return ['type' => 'html-embed', 'order' => $order, 'data' => ['html' => $html]];
    }

    public function test_editor_cannot_add_an_html_embed_but_admin_can(): void
    {
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading(), $this->embed('<script>evil()</script>')]], $this->apiHeaders())
            ->assertForbidden();
        $this->assertSame(0, Block::where('blockable_id', $this->page->id)->count());

        $this->actingAsAdmin()
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading(), $this->embed('<div id="widget"></div>')]], $this->apiHeaders())
            ->assertOk();
        $this->assertSame(1, Block::where('blockable_id', $this->page->id)->where('type', 'html-embed')->count());
    }

    public function test_editor_can_edit_around_an_existing_embed_but_not_change_it(): void
    {
        $this->actingAsAdmin()
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading(), $this->embed('<div id="widget"></div>')]], $this->apiHeaders())
            ->assertOk();
        $tree = app(BlockService::class)->getBlockTree($this->page);
        $embedId = collect($tree)->firstWhere('type', 'html-embed')['id'];

        // Same embed (same id + html), heading edited → allowed
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [
                $this->heading('Edited'),
                ['id' => $embedId, 'type' => 'html-embed', 'order' => 1, 'data' => ['html' => '<div id="widget"></div>']],
            ]], $this->apiHeaders())
            ->assertOk();
        $this->assertStringContainsString('Edited', json_encode(app(BlockService::class)->getBlockTree($this->page)));

        // Embed html changed → refused, nothing written
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [
                $this->heading('Again'),
                ['id' => $embedId, 'type' => 'html-embed', 'order' => 1, 'data' => ['html' => '<script>evil()</script>']],
            ]], $this->apiHeaders())
            ->assertForbidden();
        $this->assertStringNotContainsString('evil', json_encode(app(BlockService::class)->getBlockTree($this->page)));
        $this->assertStringNotContainsString('Again', json_encode(app(BlockService::class)->getBlockTree($this->page)));

        // Removing the embed is not creating executable HTML → allowed
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading('No embed')]], $this->apiHeaders())
            ->assertOk();
    }

    public function test_editor_cannot_set_or_change_raw_html(): void
    {
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading()], 'raw_html' => '<script>x()</script>'], $this->apiHeaders())
            ->assertForbidden();
        $this->assertNull($this->page->fresh()->raw_html);

        $this->actingAsAdmin()
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading()], 'raw_html' => '<main>trusted</main>'], $this->apiHeaders())
            ->assertOk();
        $this->assertSame('<main>trusted</main>', $this->page->fresh()->raw_html);

        // Editor re-sending the unchanged raw_html (Ctrl+S round trip) is fine…
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading('Two')], 'raw_html' => '<main>trusted</main>'], $this->apiHeaders())
            ->assertOk();
        // …a save that omits raw_html must not wipe it…
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading('Three')]], $this->apiHeaders())
            ->assertOk();
        $this->assertSame('<main>trusted</main>', $this->page->fresh()->raw_html);
        // …and changing it is refused.
        $this->actingAs($this->editor, 'sanctum')
            ->putJson($this->url(), ['overwrite' => true, 'blocks' => [$this->heading()], 'raw_html' => '<main>changed</main>'], $this->apiHeaders())
            ->assertForbidden();
        $this->assertSame('<main>trusted</main>', $this->page->fresh()->raw_html);
    }

    public function test_editor_cannot_restore_a_version_that_introduces_an_embed(): void
    {
        app(BlockService::class)->syncBlocks($this->page, [$this->heading('Current')]);
        $v = PageVersion::create([
            'page_id' => $this->page->id,
            'blocks_snapshot' => [$this->heading('Old'), $this->embed('<script>old()</script>')],
            'seo_snapshot' => [],
            'published_by' => $this->owner->id,
            'published_at' => now(),
            'version_number' => 1,
        ]);

        $this->actingAs($this->editor, 'sanctum')
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions/{$v->id}/restore", [], $this->apiHeaders())
            ->assertForbidden();
        $this->assertStringContainsString('Current', json_encode(app(BlockService::class)->getBlockTree($this->page)));

        $this->actingAsAdmin()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions/{$v->id}/restore", [], $this->apiHeaders())
            ->assertOk();
        $this->assertStringContainsString('old()', json_encode(app(BlockService::class)->getBlockTree($this->page)));
    }

    public function test_version_restore_requires_the_version_to_belong_to_the_page(): void
    {
        $other = Page::factory()->create(['site_id' => $this->site->id]);
        $v = PageVersion::create([
            'page_id' => $other->id,
            'blocks_snapshot' => [$this->heading('Other page')],
            'seo_snapshot' => [],
            'published_by' => $this->owner->id,
            'published_at' => now(),
            'version_number' => 1,
        ]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions/{$v->id}/restore", [], $this->apiHeaders())
            ->assertNotFound();
    }

    public function test_editor_cannot_inline_patch_an_embed_field(): void
    {
        app(BlockService::class)->syncBlocks($this->page, [$this->heading(), $this->embed('<div id="w"></div>')]);
        $block = Block::where('blockable_id', $this->page->id)->where('type', 'html-embed')->firstOrFail();

        $this->actingAs($this->editor, 'sanctum')
            ->patchJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/inline/blocks", [
                'patches' => [['block' => $block->id, 'field' => 'html', 'value' => '<script>x()</script>']],
            ], $this->apiHeaders())
            ->assertForbidden();
        $this->assertSame('<div id="w"></div>', $block->fresh()->data['html']);
    }
}
