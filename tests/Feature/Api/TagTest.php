<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\Site;
use App\Models\Tag;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeTag(string $name = null): Tag
    {
        $name = $name ?? 'Tag-' . Str::random(4);
        return Tag::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::random(3)]);
    }

    public function test_can_list_tags(): void
    {
        $this->makeTag('Alpha');
        $this->makeTag('Beta');
        $this->makeTag('Gamma');

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/tags")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_tag(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/tags", [
                'name' => 'Design',
                'slug' => 'design',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Design');

        $this->assertDatabaseHas('tags', ['name' => 'Design', 'site_id' => $this->site->id]);
    }

    public function test_can_update_tag(): void
    {
        $tag = $this->makeTag();

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/tags/{$tag->id}", [
                'name' => 'Renamed',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_can_delete_tag(): void
    {
        $tag = $this->makeTag();

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/tags/{$tag->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_merge_moves_posts_to_target_tag(): void
    {
        $source = $this->makeTag('Source');
        $target = $this->makeTag('Target');
        $post = Post::factory()->published()->create(['site_id' => $this->site->id]);
        $post->tags()->attach($source->id);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/tags/{$source->id}/merge", [
                'target_tag_id' => $target->id,
            ])
            ->assertStatus(200);

        // Post should now be tagged with the target
        $this->assertTrue($target->fresh()->posts()->where('taggable_id', $post->id)->exists());
        // Source tag should be deleted
        $this->assertDatabaseMissing('tags', ['id' => $source->id]);
    }
}
