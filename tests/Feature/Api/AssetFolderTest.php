<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetFolderTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function headers(): array
    {
        return array_merge($this->apiHeaders(), ['Accept' => 'application/json']);
    }

    public function test_create_list_and_delete_folders(): void
    {
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/asset-folders", ['name' => 'Лампи'], $this->headers())
            ->assertStatus(201)->assertJsonPath('data.path', 'Лампи');
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/asset-folders", ['name' => '2026', 'parent' => 'Лампи'], $this->headers())
            ->assertStatus(201)->assertJsonPath('data.path', 'Лампи/2026')->assertJsonPath('data.parent', 'Лампи');

        // Same folder again is idempotent
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/asset-folders", ['name' => 'Лампи'], $this->headers())
            ->assertStatus(200);

        // Illegal characters are rejected
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/asset-folders", ['name' => 'bad<name>'], $this->headers())
            ->assertStatus(422);

        $list = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/asset-folders", $this->headers())
            ->assertOk()->json('data');
        $this->assertSame(['Лампи', 'Лампи/2026'], array_column($list, 'path'));

        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$this->site->id}/asset-folders", ['path' => 'Лампи'], $this->headers())
            ->assertStatus(204);
        $this->assertSame(0, AssetFolder::where('site_id', $this->site->id)->count());
    }

    public function test_upload_into_folder_and_filter_listing(): void
    {
        $url = "/api/v1/sites/{$this->site->id}/assets";

        $inFolder = $this->actingAsOwner()->post($url, ['file' => UploadedFile::fake()->image('a.jpg', 50, 50), 'folder' => 'Лампи/2026'], $this->headers())
            ->assertStatus(201)->assertJsonPath('data.folder', 'Лампи/2026')->json('data.id');
        $this->actingAsOwner()->post($url, ['file' => UploadedFile::fake()->image('b.jpg', 60, 40)], $this->headers())
            ->assertStatus(201)->assertJsonPath('data.folder', null);

        // Implicit folders (from asset paths) are listed with their ancestors and counts
        $folders = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/asset-folders", $this->headers())->json();
        $this->assertSame(['Лампи', 'Лампи/2026'], array_column($folders['data'], 'path'));
        $this->assertSame(1, $folders['data'][1]['count']);
        $this->assertSame(1, $folders['root_count']);

        // folder= (empty) → root only; folder=path → that folder; absent → all
        $this->assertCount(1, $this->actingAsOwner()->getJson($url . '?folder=', $this->headers())->json('data'));
        $this->assertCount(1, $this->actingAsOwner()->getJson($url . '?folder=' . rawurlencode('Лампи/2026'), $this->headers())->json('data'));
        $this->assertCount(2, $this->actingAsOwner()->getJson($url, $this->headers())->json('data'));
        $this->assertCount(1, $this->actingAsOwner()->getJson($url . '?q=A', $this->headers())->json('data'));

        // Move an asset to another folder via PATCH
        $this->actingAsOwner()->patchJson("{$url}/{$inFolder}", ['folder' => 'Друга'], $this->headers())
            ->assertOk()->assertJsonPath('data.folder', 'Друга');
        $this->assertSame('Друга', Asset::find($inFolder)->folder);

        // Deleting a folder moves its assets to the parent, never deletes files
        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$this->site->id}/asset-folders", ['path' => 'Друга'], $this->headers())
            ->assertStatus(204);
        $this->assertNull(Asset::find($inFolder)->folder);
        $this->assertSame(2, Asset::where('site_id', $this->site->id)->count());
    }
}
