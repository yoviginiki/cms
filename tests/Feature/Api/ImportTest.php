<?php

namespace Tests\Feature\Api;

use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImportTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_upload_import_file(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss xmlns:wp="http://wordpress.org/export/1.2/"><channel><title>Test</title><wp:wxr_version>1.2</wp:wxr_version></channel></rss>';
        $file = UploadedFile::fake()->createWithContent('export.xml', $xml);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/import/upload", [
                'file' => $file,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['import_id', 'filename', 'file_size']]);
    }

    public function test_can_check_import_status(): void
    {
        $importId = 'test-import-123';
        Cache::put("import:{$importId}", [
            'status' => 'uploaded',
            'message' => 'Ready',
            'site_id' => $this->site->id,
        ], now()->addHour());

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/import/{$importId}/status")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'uploaded');
    }

    public function test_status_returns_404_for_unknown_import(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/import/nonexistent/status")
            ->assertStatus(404);
    }

    public function test_editor_cannot_upload(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss><channel></channel></rss>';
        $file = UploadedFile::fake()->createWithContent('export.xml', $xml);

        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/import/upload", [
                'file' => $file,
            ])
            ->assertStatus(403);
    }
}
