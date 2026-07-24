<?php

namespace Tests\Feature;

use App\Domain\Migration\Jobs\RunMigrationToolJob;
use App\Domain\Migration\Support\MigrationRunStore;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MigrationApiTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        config(['queue.default' => 'sync']);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/migration/' . $this->site->slug));
        parent::tearDown();
    }

    public function test_starting_a_run_queues_the_job_and_records_it(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)->postJson("/api/v1/sites/{$this->site->id}/migration/runs", [
            'tool' => 'diff',
            'origin' => 'https://example.com',
            'options' => ['limit' => 3, 'include_home' => true, 'screenshots' => false],
        ]);

        $response->assertStatus(202);
        $runId = $response->json('data.id');
        $this->assertSame('queued', $response->json('data.status'));
        Queue::assertPushed(RunMigrationToolJob::class, fn ($job) => $job->runId === $runId && $job->siteId === $this->site->id);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/sites/{$this->site->id}/migration/runs/{$runId}")
            ->assertOk()
            ->assertJsonPath('data.tool', 'diff');

        $this->actingAs($this->owner)
            ->getJson("/api/v1/sites/{$this->site->id}/migration/runs")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rejects_invalid_tools_and_private_origins(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)->postJson("/api/v1/sites/{$this->site->id}/migration/runs", [
            'tool' => 'rm-rf', 'origin' => 'https://example.com',
        ])->assertStatus(422);

        $this->actingAs($this->owner)->postJson("/api/v1/sites/{$this->site->id}/migration/runs", [
            'tool' => 'diff', 'origin' => 'http://127.0.0.1/internal',
        ])->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_users_from_another_tenant_cannot_start_runs(): void
    {
        Queue::fake();

        $stranger = \App\Models\User::factory()->create([
            'tenant_id' => \App\Models\Tenant::factory()->create()->id,
        ]);

        $this->actingAs($stranger)->postJson("/api/v1/sites/{$this->site->id}/migration/runs", [
            'tool' => 'diff', 'origin' => 'https://example.com',
        ])->assertStatus(404); // RLS: the site does not exist for another tenant

        Queue::assertNothingPushed();
    }

    public function test_artifact_endpoint_serves_files_and_blocks_traversal(): void
    {
        $dir = MigrationRunStore::artifactDir($this->site);
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/redirects.json", '{"mapped":[]}');

        $this->actingAs($this->owner)
            ->get("/api/v1/sites/{$this->site->id}/migration/artifacts/redirects.json")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $this->actingAs($this->owner)
            ->get("/api/v1/sites/{$this->site->id}/migration/artifacts/..%2F..%2F..%2F.env")
            ->assertNotFound();
    }
}
