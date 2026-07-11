<?php

namespace Tests\Feature\Library;

use App\Models\BlockTemplate;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Builder Experience P1 — The Library API (extends block_templates).
 */
class LibraryTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function heading(string $text = 'Hi'): array
    {
        return ['type' => 'heading', 'data' => ['text' => $text]];
    }

    private function base(): string
    {
        return "/api/v1/sites/{$this->site->id}/block-templates";
    }

    public function test_store_saves_a_library_item_with_kind_and_tags(): void
    {
        $resp = $this->actingAsOwner()->postJson($this->base(), [
            'name' => 'Hero A',
            'category' => 'heroes',
            'kind' => 'section',
            'tags' => ['marketing', 'dark'],
            'blocks_data' => [$this->heading('Big')],
        ])->assertStatus(201);

        $resp->assertJsonPath('data.name', 'Hero A')
            ->assertJsonPath('data.kind', 'section')
            ->assertJsonPath('data.tags', ['marketing', 'dark'])
            ->assertJsonPath('data.slug', 'hero-a');
        // HasUuids must mint the id (uuid column has no DB default)
        $this->assertNotNull($resp->json('data.id'));
        $this->assertDatabaseHas('block_templates', ['id' => $resp->json('data.id'), 'is_system' => false]);
    }

    public function test_index_filters_by_kind_tag_and_search(): void
    {
        $this->actingAsOwner()->postJson($this->base(), ['name' => 'Alpha Section', 'kind' => 'section', 'tags' => ['x'], 'blocks_data' => [$this->heading()]]);
        $this->actingAsOwner()->postJson($this->base(), ['name' => 'Beta Row', 'kind' => 'row', 'tags' => ['y'], 'blocks_data' => [$this->heading()]]);

        $this->actingAsOwner()->getJson($this->base() . '?kind=section')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Alpha Section');
        $this->actingAsOwner()->getJson($this->base() . '?tag=y')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beta Row');
        $this->actingAsOwner()->getJson($this->base() . '?q=alpha')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_update_renames_and_retags(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'Old', 'blocks_data' => [$this->heading()]])->json('data.id');

        $this->actingAsOwner()->patchJson("{$this->base()}/{$id}", ['name' => 'New', 'category' => 'cta', 'tags' => ['z']])
            ->assertOk()->assertJsonPath('data.name', 'New')->assertJsonPath('data.category', 'cta')->assertJsonPath('data.tags', ['z']);
    }

    public function test_import_sanitizes_and_rejects_unknown_types(): void
    {
        // XSS in a text field is stripped on import
        $resp = $this->actingAsOwner()->postJson("{$this->base()}/import", [
            'name' => 'Imported',
            'blocks_data' => [['type' => 'heading', 'data' => ['text' => 'Hello <script>alert(1)</script>']]],
        ])->assertStatus(201);
        $stored = BlockTemplate::find($resp->json('data.id'));
        $this->assertStringNotContainsString('<script>', json_encode($stored->blocks_data));

        // unknown block type is refused (422, no partial import)
        $this->actingAsOwner()->postJson("{$this->base()}/import", [
            'name' => 'Bad', 'blocks_data' => [['type' => 'totally-not-a-block', 'data' => []]],
        ])->assertStatus(422)->assertJsonPath('error', 'This library item uses an unknown block type: totally-not-a-block.');
    }

    public function test_destroy_removes_own_item(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'Tmp', 'blocks_data' => [$this->heading()]])->json('data.id');
        $this->actingAsOwner()->deleteJson("{$this->base()}/{$id}")->assertOk();
        $this->assertDatabaseMissing('block_templates', ['id' => $id]);
    }

    /**
     * A shared system item (site_id NULL, is_system true). A seeder creates these
     * outside RLS; the owner-only WITH CHECK blocks NULL-site writes, so the test
     * briefly disables RLS to plant one (rolled back with the per-test txn).
     */
    private function makeSystemItem(string $name = 'System Hero'): BlockTemplate
    {
        DB::statement('ALTER TABLE block_templates DISABLE ROW LEVEL SECURITY');
        try {
            $sys = new BlockTemplate();
            $sys->forceFill([
                'site_id' => null,
                'name' => $name,
                'category' => 'system',
                'kind' => 'section',
                'blocks_data' => [$this->heading('Sys')],
                'is_system' => true,
            ]);
            $sys->id = Str::uuid()->toString();
            $sys->save();
            return $sys;
        } finally {
            DB::statement('ALTER TABLE block_templates ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE block_templates FORCE ROW LEVEL SECURITY');
        }
    }

    public function test_system_items_are_readable_but_not_editable(): void
    {
        $sys = $this->makeSystemItem();

        // visible in the list
        $names = collect($this->actingAsOwner()->getJson($this->base())->json('data'))->pluck('name');
        $this->assertTrue($names->contains('System Hero'));

        // cannot be edited or deleted
        $this->actingAsOwner()->patchJson("{$this->base()}/{$sys->id}", ['name' => 'Hacked'])->assertStatus(403);
        $this->actingAsOwner()->deleteJson("{$this->base()}/{$sys->id}")->assertStatus(403);
    }

    public function test_cross_tenant_item_is_invisible(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'Mine', 'blocks_data' => [$this->heading()]])->json('data.id');

        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($userB);
        $siteB = Site::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($userB)->getJson("/api/v1/sites/{$siteB->id}/block-templates/{$id}")->assertStatus(404);
    }
}
