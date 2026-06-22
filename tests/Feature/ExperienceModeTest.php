<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class ExperienceModeTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── Default value ───

    public function test_page_defaults_to_standard(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $this->assertEquals('standard', $page->experience_mode);
    }

    public function test_post_defaults_to_standard(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id]);
        $this->assertEquals('standard', $post->experience_mode);
    }

    // ─── Validation ───

    public function test_page_accepts_cinematic(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);

        $response = $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}", [
                'experience_mode' => 'cinematic',
            ], $this->apiHeaders());

        $response->assertOk();
        $this->assertEquals('cinematic', $page->fresh()->experience_mode);
    }

    public function test_page_accepts_standard(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
        ]);

        $response = $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}", [
                'experience_mode' => 'standard',
            ], $this->apiHeaders());

        $response->assertOk();
        $this->assertEquals('standard', $page->fresh()->experience_mode);
    }

    public function test_page_rejects_invalid_experience_mode(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);

        $response = $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}", [
                'experience_mode' => 'invalid',
            ], $this->apiHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['experience_mode']);
    }

    public function test_post_rejects_invalid_experience_mode(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id]);

        $response = $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}", [
                'experience_mode' => 'broken',
            ], $this->apiHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['experience_mode']);
    }

    // ─── API serialization round-trip ───

    public function test_page_api_returns_experience_mode(): void
    {
        $page = Page::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
        ]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}", $this->apiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.experience_mode', 'cinematic');
    }

    public function test_post_api_returns_experience_mode(): void
    {
        $post = Post::factory()->create([
            'site_id' => $this->site->id,
            'experience_mode' => 'cinematic',
        ]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}", $this->apiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.experience_mode', 'cinematic');
    }

    // ─── Existing pages unaffected ───

    public function test_existing_pages_remain_standard_when_updating_other_fields(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $this->assertEquals('standard', $page->experience_mode);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}", [
                'title' => 'Updated Title',
            ], $this->apiHeaders());

        $this->assertEquals('standard', $page->fresh()->experience_mode);
    }

    // ─── Down migration ───

    public function test_experience_mode_column_exists_on_pages(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('pages', 'experience_mode')
        );
    }

    public function test_experience_mode_column_exists_on_posts(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('posts', 'experience_mode')
        );
    }
}
