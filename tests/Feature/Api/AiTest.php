<?php

namespace Tests\Feature\Api;

use App\Models\Site;
use Tests\TestCase;

class AiTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_generate_returns_503_when_ai_disabled(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/ai/generate', [
                'prompt' => 'Write a headline about cats',
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI features are not enabled.');
    }

    public function test_rewrite_returns_503_when_ai_disabled(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/ai/rewrite', [
                'content' => 'Hello world',
                'instruction' => 'Make it formal',
            ])
            ->assertStatus(503);
    }

    public function test_translate_returns_503_when_ai_disabled(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/ai/translate', [
                'content' => 'Hello',
                'language' => 'Bulgarian',
            ])
            ->assertStatus(503);
    }

    public function test_generate_validates_prompt(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/ai/generate', [])
            ->assertStatus(422);
    }

    public function test_rewrite_validates_content_and_instruction(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/ai/rewrite', ['content' => 'Hello'])
            ->assertStatus(422);
    }

    public function test_unauthenticated_cannot_use_ai(): void
    {
        $this->postJson('/api/v1/ai/generate', ['prompt' => 'test'])
            ->assertStatus(401);
    }
}
