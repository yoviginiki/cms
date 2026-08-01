<?php

namespace Tests\Feature\InlineEdit;

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * HTTP contract for the inline save/draft/export API (Phase 3).
 *
 * Requires a test database (RefreshDatabase). It is CI-ready but is NOT run in
 * this session because .env.testing / a dev database are not configured — the
 * pure write rules (422 unknown path, 409 conflict, 403 shared entity, sanitize)
 * are proven DB-free in tests/Unit/InlineEdit/InlineEditServiceTest.php.
 */
final class InlineEditApiTest extends TestCase
{
    private Site $site;
    private Page $page;
    private Block $heading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);

        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        $this->heading = Block::factory()->create([
            'blockable_id' => $this->page->id,
            'blockable_type' => 'page',
            'type' => 'heading',
            'data' => ['text' => 'Original', 'level' => 'h1'],
            'order' => 0,
        ]);
    }

    private function base(): string
    {
        return "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/inline";
    }

    public function test_session_returns_version_and_block_hashes(): void
    {
        $res = $this->actingAsOwner()->getJson($this->base() . '/session', $this->apiHeaders());
        // session is POST
        $res = $this->actingAsOwner()->postJson($this->base() . '/session', [], $this->apiHeaders());

        $res->assertOk()
            ->assertJsonStructure(['session_id', 'version', 'blocks' => [['block', 'hash']]]);
        $this->assertSame($this->heading->id, $res->json('blocks.0.block'));
    }

    public function test_patch_updates_heading_text_in_draft(): void
    {
        $session = $this->actingAsOwner()->postJson($this->base() . '/session', [], $this->apiHeaders());
        $hash = $session->json('blocks.0.hash');

        $res = $this->actingAsOwner()->patchJson($this->base() . '/blocks', [
            'expected_version' => $session->json('version'),
            'patches' => [
                ['block' => $this->heading->id, 'field' => 'text', 'value' => 'Edited inline', 'block_hash' => $hash],
            ],
        ], $this->apiHeaders());

        $res->assertOk();
        $this->assertSame('Edited inline', $this->heading->fresh()->data['text']);
    }

    public function test_unknown_field_path_is_rejected_422(): void
    {
        $this->actingAsOwner()->patchJson($this->base() . '/blocks', [
            'patches' => [['block' => $this->heading->id, 'field' => 'bogus', 'value' => 'x']],
        ], $this->apiHeaders())->assertStatus(422);
    }

    public function test_stale_version_is_conflict_409(): void
    {
        $this->actingAsOwner()->patchJson($this->base() . '/blocks', [
            'expected_version' => '999:2000-01-01 00:00:00',
            'patches' => [['block' => $this->heading->id, 'field' => 'text', 'value' => 'x']],
        ], $this->apiHeaders())->assertStatus(409);
    }

    public function test_stale_block_hash_is_conflict_409(): void
    {
        $session = $this->actingAsOwner()->postJson($this->base() . '/session', [], $this->apiHeaders());
        // mutate the block so the session hash is now stale
        $this->heading->update(['data' => ['text' => 'Changed elsewhere', 'level' => 'h1']]);

        $this->actingAsOwner()->patchJson($this->base() . '/blocks', [
            'patches' => [[
                'block' => $this->heading->id, 'field' => 'text', 'value' => 'x',
                'block_hash' => $session->json('blocks.0.hash'),
            ]],
        ], $this->apiHeaders())->assertStatus(409);
    }

    public function test_shared_entity_block_is_forbidden_403(): void
    {
        $ref = Block::factory()->create([
            'blockable_id' => $this->page->id, 'blockable_type' => 'page',
            'type' => 'slider_ref', 'data' => ['sliderId' => '00000000-0000-0000-0000-000000000001'], 'order' => 1,
        ]);

        $this->actingAsOwner()->patchJson($this->base() . '/blocks', [
            'patches' => [['block' => $ref->id, 'field' => 'sliderId', 'value' => 'x']],
        ], $this->apiHeaders())->assertStatus(403);
    }

    public function test_draft_materializes_a_page_version(): void
    {
        $this->actingAsOwner()->postJson($this->base() . '/draft', [], $this->apiHeaders())
            ->assertOk()
            ->assertJsonStructure(['version_id', 'version_number']);
    }

    public function test_export_json_and_html(): void
    {
        $this->actingAsOwner()->get($this->base() . '/export?format=json', $this->apiHeaders())
            ->assertOk()->assertJsonStructure(['blocks']);

        $html = $this->actingAsOwner()->get($this->base() . '/export?format=html', $this->apiHeaders());
        $html->assertOk();
        // Export renders through Publish mode → no inline-edit attributes.
        $this->assertStringNotContainsString('data-sp-', $html->getContent());
    }

    public function test_viewer_cannot_open_session(): void
    {
        $viewer = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'viewer']);
        $this->actingAs($viewer, 'sanctum')
            ->postJson($this->base() . '/session', [], $this->apiHeaders())
            ->assertStatus(403);
    }
}
