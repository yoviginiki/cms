<?php

namespace Tests\Feature\Modules;

use App\Models\Block;
use App\Models\Module;
use App\Models\ModuleTenant;
use App\Models\ModuleToken;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class CultureDraftEndpointTest extends TestCase
{
    private const URL = '/api/modules/culture-engine/drafts';

    private Module $module;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);

        $this->module = Module::create([
            'key' => 'culture-engine',
            'name' => 'Culture Engine',
            'enabled_globally' => true,
        ]);
        ModuleTenant::create([
            'module_id' => $this->module->id,
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function token(array $abilities = ['drafts:create'], bool $revoked = false): string
    {
        [, $plaintext] = ModuleToken::issue([
            'module_id' => $this->module->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Engine',
            'abilities' => $abilities,
            'revoked_at' => $revoked ? now() : null,
        ]);
        return $plaintext;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'weekly-cultural-guide',
            'title' => 'This week in Sofia',
            'slug' => 'this-week-in-sofia',
            'status' => 'draft',
            'tags' => ['culture', 'sofia'],
            'excerpt' => 'The best of the week.',
            'blocks' => [[
                'type' => 'bulletin-section',
                'order' => 0,
                'data' => ['title' => 'Concerts'],
                'children' => [[
                    'type' => 'event-card',
                    'order' => 0,
                    'data' => [
                        'title' => 'Jazz night',
                        'city' => 'Sofia',
                        'venue' => 'NDK',
                        'is_free' => true,
                        'ticket_url' => 'https://tickets.example/jazz',
                        'short_description' => '<script>alert(1)</script>Great <b onclick="x()">show</b>',
                    ],
                ]],
            ]],
            'metadata' => ['culture_engine_bulletin_id' => 123, 'week' => '2026-W33'],
        ], $overrides);
    }

    public function test_happy_path_creates_a_sanitized_draft(): void
    {
        $res = $this->withToken($this->token())
            ->postJson(self::URL, $this->payload(), ['Idempotency-Key' => 'bulletin-2026-W33']);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'draft')
            ->assertJsonStructure(['external_id', 'status', 'admin_url']);

        $this->setTenantScope($this->owner);
        $post = Post::find($res->json('external_id'));
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertSame('This week in Sofia', $post->title);
        $this->assertEqualsCanonicalizing(['culture', 'sofia'], $post->tags()->pluck('name')->all());

        // Blocks persisted as a nested tree (section → event-card).
        $section = Block::where('blockable_id', $post->id)->where('type', 'bulletin-section')->first();
        $card = Block::where('blockable_id', $post->id)->where('type', 'event-card')->first();
        $this->assertNotNull($section);
        $this->assertNotNull($card);
        $this->assertSame($section->id, $card->parent_block_id);

        // Hostile HTML in short_description was neutralized before persistence.
        $desc = $card->data['short_description'];
        $this->assertStringNotContainsString('<script', $desc);
        $this->assertStringNotContainsString('onclick', $desc);
    }

    public function test_idempotent_replay_returns_same_draft(): void
    {
        $token = $this->token();

        $first = $this->withToken($token)
            ->postJson(self::URL, $this->payload(), ['Idempotency-Key' => 'k1']);
        $first->assertStatus(201);

        $replay = $this->withToken($token)
            ->postJson(self::URL, $this->payload(), ['Idempotency-Key' => 'k1']);
        $replay->assertStatus(200)
            ->assertJsonPath('external_id', $first->json('external_id'));

        $this->setTenantScope($this->owner);
        $this->assertSame(1, Post::count());
    }

    public function test_same_key_different_payload_conflicts(): void
    {
        $token = $this->token();

        $this->withToken($token)
            ->postJson(self::URL, $this->payload(), ['Idempotency-Key' => 'k2'])
            ->assertStatus(201);

        $this->withToken($token)
            ->postJson(self::URL, $this->payload(['title' => 'Different title']), ['Idempotency-Key' => 'k2'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_key_conflict');

        $this->setTenantScope($this->owner);
        $this->assertSame(1, Post::count());
    }

    public function test_unknown_block_types_are_rejected_and_persist_nothing(): void
    {
        $payload = $this->payload([
            'blocks' => [
                ['type' => 'bulletin-section', 'order' => 0, 'data' => [], 'children' => [
                    ['type' => 'totally-made-up', 'order' => 0, 'data' => []],
                ]],
                ['type' => 'another-fake', 'order' => 1, 'data' => []],
            ],
        ]);

        $this->withToken($this->token())
            ->postJson(self::URL, $payload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'unknown_block_types')
            ->assertJsonFragment(['types' => ['totally-made-up', 'another-fake']]);

        $this->setTenantScope($this->owner);
        $this->assertSame(0, Post::count());
    }

    public function test_disabled_module_forbids_even_with_valid_token(): void
    {
        ModuleTenant::where('module_id', $this->module->id)
            ->where('tenant_id', $this->tenant->id)
            ->update(['enabled' => false]);

        $this->withToken($this->token())
            ->postJson(self::URL, $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('error', 'module_disabled');

        $this->setTenantScope($this->owner);
        $this->assertSame(0, Post::count());
    }

    public function test_missing_ability_is_forbidden(): void
    {
        $this->withToken($this->token(abilities: ['something:else']))
            ->postJson(self::URL, $this->payload())
            ->assertStatus(403);
    }

    public function test_missing_token_is_unauthorized(): void
    {
        $this->postJson(self::URL, $this->payload())->assertStatus(401);
    }
}
