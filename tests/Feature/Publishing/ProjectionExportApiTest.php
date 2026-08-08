<?php

namespace Tests\Feature\Publishing;

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Export consumer — HTTP delivery contract. Read-only, tenant-scoped.
 */
class ProjectionExportApiTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);

        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'about']);
        Block::factory()->create([
            'blockable_id' => $this->page->id, 'blockable_type' => 'page',
            'type' => 'heading', 'data' => ['text' => 'Api Heading', 'level' => 'h2'], 'order' => 0,
        ]);
        Block::factory()->create([
            'blockable_id' => $this->page->id, 'blockable_type' => 'page',
            'type' => 'image', 'data' => ['url' => 'https://cdn/a.jpg', 'alt' => 'Api Alt'], 'order' => 1,
        ]);
    }

    private function url(string $format): string
    {
        return "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/projection?format={$format}";
    }

    public function test_json_export_endpoint(): void
    {
        $res = $this->actingAsOwner()->getJson($this->url('json'), $this->apiHeaders());
        $res->assertOk();
        $res->assertJsonPath('projection_version', '1.0');
        $this->assertNotEmpty($res->json('schema_org.@graph'));
    }

    public function test_markdown_export_endpoint(): void
    {
        $res = $this->actingAsOwner()->get($this->url('md'), $this->apiHeaders());
        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('## Api Heading', $res->getContent());
        $this->assertStringContainsString('![Api Alt](https://cdn/a.jpg)', $res->getContent());
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->actingAsOwner()->get($this->url('xml'), $this->apiHeaders())->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson($this->url('json'))->assertUnauthorized();
    }
}
