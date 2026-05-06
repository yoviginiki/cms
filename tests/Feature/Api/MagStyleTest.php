<?php

namespace Tests\Feature\Api;

use App\Domain\Magazine\Models\MagStyle;
use App\Models\Site;
use Tests\TestCase;

class MagStyleTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeStyle(array $attrs = []): MagStyle
    {
        return MagStyle::create(array_merge([
            'site_id' => $this->site->id,
            'name' => 'Body Text',
            'type' => 'paragraph',
            'properties' => ['font_size' => 12, 'line_height' => 1.4],
            'sort_order' => 0,
        ], $attrs));
    }

    public function test_can_list_styles(): void
    {
        $this->makeStyle();

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-styles")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_style(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/magazine-styles", [
                'name' => 'Headline',
                'type' => 'paragraph',
                'properties' => ['font_size' => 24, 'font_weight' => 'bold'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Headline');

        $this->assertDatabaseHas('mag_styles', ['name' => 'Headline', 'site_id' => $this->site->id]);
    }

    public function test_can_show_style(): void
    {
        $style = $this->makeStyle();

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-styles/{$style->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $style->id);
    }

    public function test_can_update_style(): void
    {
        $style = $this->makeStyle();

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/magazine-styles/{$style->id}", [
                'name' => 'Updated Name',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_style(): void
    {
        $style = $this->makeStyle();

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/magazine-styles/{$style->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mag_styles', ['id' => $style->id]);
    }

    public function test_rejects_invalid_style_type(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/magazine-styles", [
                'name' => 'Bad',
                'type' => 'invalid-type',
                'properties' => [],
            ])
            ->assertStatus(422);
    }
}
