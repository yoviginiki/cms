<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/** Users → edit → "Page builder по подразбиране": new pages/posts open in that builder. */
class UserDefaultEditorModeTest extends TestCase
{
    public function test_new_content_uses_the_creators_default_builder(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()->putJson("/api/v1/users/{$this->owner->id}", ['default_editor_mode' => 'canvas'], $this->apiHeaders())->assertOk()
            ->assertJsonPath('data.default_editor_mode', 'canvas');
        $this->owner->refresh(); // a real request loads the user fresh

        $postId = $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/posts", ['title' => 'Нов', 'status' => 'draft'], $this->apiHeaders())->assertCreated()->json('data.id');
        $this->assertSame('canvas', Post::find($postId)->editor_mode);
        $pageId = $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/pages", ['title' => 'Нова', 'status' => 'draft'], $this->apiHeaders())->assertCreated()->json('data.id');
        $this->assertSame('canvas', Page::find($pageId)->editor_mode);

        // simple is posts-only: pages fall back to the system default
        $this->actingAsOwner()->putJson("/api/v1/users/{$this->owner->id}", ['default_editor_mode' => 'simple'], $this->apiHeaders())->assertOk();
        $this->owner->refresh();
        $pageId = $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/pages", ['title' => 'Втора', 'status' => 'draft'], $this->apiHeaders())->json('data.id');
        $this->assertNotSame('simple', Page::find($pageId)->editor_mode);

        $this->actingAsOwner()->putJson("/api/v1/users/{$this->owner->id}", ['default_editor_mode' => 'nope'], $this->apiHeaders())->assertStatus(422);
    }
}
