<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class MagEditorTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
    }

    public function test_can_get_magazine_document(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/magazine")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_sync_magazine_document(): void
    {
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/magazine", [
                'pages' => [
                    [
                        'page_number' => 1,
                        'page_size' => ['width' => 210, 'height' => 297],
                        'margins' => ['top' => 36, 'left' => 36, 'right' => 36, 'bottom' => 36],
                        'bleed' => ['top' => 9, 'right' => 9, 'bottom' => 9, 'left' => 9],
                        'columns' => ['count' => 1, 'gutter' => 12],
                        'baseline_grid' => ['increment' => 14, 'start' => 36],
                    ],
                ],
                'elements' => [
                    [
                        'type' => 'text',
                        'x' => 20,
                        'y' => 30,
                        'width' => 170,
                        'height' => 50,
                        'page_number' => 1,
                        'data' => ['content' => 'Hello'],
                        'style' => [],
                        'text_wrap' => ['type' => 'none', 'offset' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0], 'side' => 'both'],
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_add_page(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/magazine/pages", [
                'after_page' => 0,
            ])
            ->assertStatus(201);
    }

    public function test_can_delete_page(): void
    {
        // First add a page so there's something to delete
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/magazine/pages", [
                'after_page' => 0,
            ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/magazine/pages/1")
            ->assertStatus(200);
    }

    public function test_sync_validates_elements(): void
    {
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/magazine", [
                'pages' => [],
                'elements' => 'invalid',
            ])
            ->assertStatus(422);
    }
}
