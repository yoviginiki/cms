<?php

namespace Tests\Feature\Api;

use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GridTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeGrid(array $attrs = []): Grid
    {
        return Grid::create(array_merge([
            'site_id' => $this->site->id,
            'name' => 'Test Grid',
            'slug' => 'test-grid-' . uniqid(),
            'col_tracks' => '1fr 1fr',
            'row_tracks' => 'auto',
            'areas' => '"main main"',
            'is_preset' => false,
        ], $attrs));
    }

    public function test_can_list_grids(): void
    {
        $this->makeGrid();

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/grids")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_grid(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/grids", [
                'name' => 'Hero Grid',
                'areas' => '"hero"',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Hero Grid');

        $this->assertDatabaseHas('grids', ['name' => 'Hero Grid', 'site_id' => $this->site->id]);
    }

    public function test_can_update_grid(): void
    {
        $grid = $this->makeGrid();

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/grids/{$grid->id}", [
                'name' => 'Renamed Grid',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed Grid');
    }

    public function test_can_delete_grid(): void
    {
        $grid = $this->makeGrid();

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/grids/{$grid->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('grids', ['id' => $grid->id]);
    }

    public function test_cannot_delete_preset_grid(): void
    {
        $grid = $this->makeGrid(['is_preset' => true]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/grids/{$grid->id}")
            ->assertStatus(422);
    }

    public function test_can_list_grid_assignments(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/grid-assignments")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_grid_assignment(): void
    {
        $grid = $this->makeGrid();

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/grid-assignments", [
                'grid_id' => $grid->id,
                'assignable_type' => 'post_type',
                'priority' => 10,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.assignable_type', 'post_type');

        $this->assertDatabaseHas('grid_assignments', ['grid_id' => $grid->id, 'site_id' => $this->site->id]);
    }

    public function test_can_delete_grid_assignment(): void
    {
        $grid = $this->makeGrid();
        $assignment = GridAssignment::create([
            'site_id' => $this->site->id,
            'grid_id' => $grid->id,
            'assignable_type' => 'post_type',
            'priority' => 5,
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/grid-assignments/{$assignment->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('grid_assignments', ['id' => $assignment->id]);
    }

    public function test_cannot_delete_default_assignment(): void
    {
        $grid = $this->makeGrid();
        $assignment = GridAssignment::create([
            'site_id' => $this->site->id,
            'grid_id' => $grid->id,
            'assignable_type' => 'default',
            'priority' => 0,
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/grid-assignments/{$assignment->id}")
            ->assertStatus(422);
    }

    public function test_editor_cannot_create_grid(): void
    {
        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/grids", [
                'name' => 'Sneaky Grid',
                'areas' => '"main"',
            ])
            ->assertStatus(403);
    }
}
