<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use Tests\TestCase;

class CollectionCrudTest extends TestCase
{
    use BuildsCollections;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    public function test_creates_collection_with_schema_via_api(): void
    {
        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/collections", [
            'name' => 'Books',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'isbn', 'label' => 'ISBN', 'type' => 'sku', 'unique' => true],
                ],
                'title_field' => 'title',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'books')
            ->assertJsonPath('data.tier', 'static')
            ->assertJsonPath('data.schema.title_field', 'title')
            ->assertJsonPath('data.schema.slug_source', 'title')
            ->assertJsonPath('data.schema.fields.1.unique', true);
    }

    public function test_creates_collection_with_empty_schema_but_blocks_records_until_designed(): void
    {
        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/collections", [
            'name' => 'Draft Idea',
            'schema' => ['fields' => []],
        ]);

        $response->assertStatus(201)->assertJsonPath('data.schema.title_field', null);

        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$response->json('data.id')}/records",
            ['data' => ['anything' => 'x']],
        )->assertStatus(422);
    }

    public function test_rejects_bad_field_key_reserved_key_and_missing_title_field(): void
    {
        $base = fn (array $fields, array $extra = []) => $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/collections", [
                'name' => 'Bad', 'schema' => ['fields' => $fields] + $extra,
            ]);

        $base([['key' => 'Bad Key!', 'label' => 'X', 'type' => 'text']])->assertStatus(422);
        $base([['key' => 'status', 'label' => 'X', 'type' => 'text']])->assertStatus(422);
        $base([['key' => 'ok', 'label' => 'X', 'type' => 'nope']])->assertStatus(422);
        // valid field but no title_field
        $base([['key' => 'ok', 'label' => 'X', 'type' => 'text']])->assertStatus(422);
        // select without options
        $base([['key' => 'ok', 'label' => 'X', 'type' => 'select']], ['title_field' => 'ok'])->assertStatus(422);
    }

    public function test_unique_toggle_rejected_on_non_unique_types(): void
    {
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/collections", [
            'name' => 'Bad',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['key' => 'flag', 'label' => 'Flag', 'type' => 'boolean', 'unique' => true],
                ],
                'title_field' => 'name',
            ],
        ])->assertStatus(422);
    }

    public function test_duplicate_collection_slug_gets_suffix(): void
    {
        $authors = $this->createAuthorsCollection($this->site);
        $this->assertSame('authors', $authors->slug);

        $second = app(\App\Domain\Collections\Services\CollectionService::class)->create($this->site, [
            'name' => 'Authors',
            'schema' => [
                'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text']],
                'title_field' => 'name',
            ],
        ]);

        $this->assertSame('authors-2', $second->slug);
    }

    public function test_update_reports_warnings_when_schema_change_affects_records(): void
    {
        $authors = $this->createAuthorsCollection($this->site);
        app(RecordService::class)->save($authors, $this->site, null, ['data' => ['name' => 'Ursula K. Le Guin']]);

        $schema = $authors->schema;
        $schema['fields'] = [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            // 'bio' removed
        ];

        $response = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/collections/{$authors->id}",
            ['name' => 'Authors', 'schema' => $schema],
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('warnings'));
        $this->assertStringContainsString('Biography', $response->json('warnings.0'));
    }

    public function test_delete_with_records_requires_force(): void
    {
        $authors = $this->createAuthorsCollection($this->site);
        app(RecordService::class)->save($authors, $this->site, null, ['data' => ['name' => 'Isaac Asimov']]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/collections/{$authors->id}")
            ->assertStatus(409)
            ->assertJsonPath('recordCount', 1);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/collections/{$authors->id}?force=1")
            ->assertOk();

        $this->assertDatabaseMissing('collections', ['id' => $authors->id]);
        $this->assertDatabaseMissing('records', ['collection_id' => $authors->id]);
    }

    public function test_delete_blocked_when_other_collections_relate_to_it(): void
    {
        $authors = $this->createAuthorsCollection($this->site);
        $this->createBooksCollection($this->site, $authors);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/collections/{$authors->id}")
            ->assertStatus(409)
            ->assertJsonPath('relationDependents.0', 'Books');
    }

    public function test_other_tenants_cannot_see_collections(): void
    {
        $this->createAuthorsCollection($this->site);

        $otherTenant = \App\Models\Tenant::factory()->create();
        $otherOwner = \App\Models\User::factory()->owner()->create(['tenant_id' => $otherTenant->id]);

        // RLS hides the site row itself from the other tenant, so route
        // binding 404s before the policy ever runs (stronger than 403).
        $this->actingAs($otherOwner, 'sanctum')
            ->getJson("/api/v1/sites/{$this->site->id}/collections")
            ->assertStatus(404);

        // RLS layer: even a raw query under the other tenant's GUC sees nothing.
        $this->setTenantScope($otherOwner);
        $this->assertSame(0, ContentCollection::count());
        $this->setTenantScope($this->owner);
        $this->assertSame(1, ContentCollection::count());
    }
}
