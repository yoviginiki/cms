<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAsOwner()->post(
            "/api/v1/sites/{$this->site->id}/assets",
            ['file' => $file],
            array_merge($this->apiHeaders(), ['Accept' => 'application/json']),
        );
    }

    public function test_can_upload_valid_image(): void
    {
        $this->upload(UploadedFile::fake()->image('photo.jpg', 120, 80))
            ->assertStatus(201);
    }

    public function test_rejects_php_file_disguised_as_image(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1; ?>'))
            ->assertStatus(422);
    }

    public function test_rejects_oversized_file(): void
    {
        // max is 100 MB (102400 KB)
        $this->upload(UploadedFile::fake()->create('big.jpg', 102401))
            ->assertStatus(422);
    }

    public function test_rejects_disallowed_extension(): void
    {
        $this->upload(UploadedFile::fake()->create('malware.exe', 10))
            ->assertStatus(422);
    }

    public function test_mime_type_must_match_extension(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('fake.jpg', 'this is plain text, not an image'))
            ->assertStatus(422);
    }

    public function test_svg_with_script_tag_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->upload(UploadedFile::fake()->createWithContent('bad.svg', $svg))
            ->assertStatus(422);
    }

    public function test_deduplicates_by_checksum(): void
    {
        $content = 'identical file body for dedup';
        $first = $this->upload(UploadedFile::fake()->createWithContent('a.txt', $content))->assertStatus(201);
        $second = $this->upload(UploadedFile::fake()->createWithContent('b.txt', $content))->assertStatus(201);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Asset::where('site_id', $this->site->id)->count());
    }
}
