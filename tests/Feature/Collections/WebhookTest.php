<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Collections v3 — webhooks: CRUD API, record-event dispatch, signed
 * delivery, retry backoff on failure.
 */
class WebhookTest extends TestCase
{
    private Site $site;
    private ContentCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->collection = app(CollectionService::class)->create($this->site, [
            'name' => 'Items',
            'tier' => 'static',
            'schema' => [
                'fields' => [['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true]],
                'title_field' => 'title',
            ],
        ]);
        config(['collections.import_skip_dns_guard' => true]); // fake hosts don't resolve
    }

    private function makeHook(array $events = ['record.created']): Webhook
    {
        $res = $this->actingAs($this->owner)->postJson("/api/v1/sites/{$this->site->id}/webhooks", [
            'url' => 'https://hooks.example.com/receiver',
            'events' => $events,
        ]);
        $res->assertCreated();
        $this->assertNotEmpty($res->json('data.secret'));

        return Webhook::findOrFail($res->json('data.id'));
    }

    public function test_crud_and_secret_shown_once(): void
    {
        $hook = $this->makeHook();

        $list = $this->actingAs($this->owner)->getJson("/api/v1/sites/{$this->site->id}/webhooks");
        $list->assertOk();
        $this->assertNull($list->json('data.0.secret'));

        $this->actingAs($this->owner)
            ->putJson("/api/v1/sites/{$this->site->id}/webhooks/{$hook->id}", [
                'url' => 'https://hooks.example.com/receiver',
                'events' => ['record.created', 'record.deleted'],
                'active' => false,
            ])->assertOk();
        $this->assertFalse($hook->fresh()->active);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/sites/{$this->site->id}/webhooks/{$hook->id}")
            ->assertOk();
        $this->assertNull(Webhook::find($hook->id));
    }

    public function test_http_url_rejected(): void
    {
        $this->actingAs($this->owner)->postJson("/api/v1/sites/{$this->site->id}/webhooks", [
            'url' => 'http://insecure.example.com/hook',
            'events' => ['record.created'],
        ])->assertStatus(422);
    }

    public function test_record_create_delivers_signed_payload(): void
    {
        $hook = $this->makeHook(['record.created']);
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);

        app(RecordService::class)->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Hello'],
        ]);
        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        $delivery = WebhookDelivery::where('webhook_id', $hook->id)->firstOrFail();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame('record.created', $delivery->event);
        $this->assertSame('Hello', $delivery->payload['data']['record']['title']);

        Http::assertSent(function ($request) use ($hook, $delivery) {
            $expected = hash_hmac('sha256', json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $hook->secret);

            return $request->url() === 'https://hooks.example.com/receiver'
                && $request->header('X-Cms-Signature')[0] === $expected
                && $request->header('X-Cms-Event')[0] === 'record.created';
        });
    }

    public function test_failed_delivery_schedules_backoff(): void
    {
        $hook = $this->makeHook(['record.created']);
        Http::fake(['hooks.example.com/*' => Http::response('boom', 500)]);

        app(RecordService::class)->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Broken'],
        ]);
        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        $delivery = WebhookDelivery::where('webhook_id', $hook->id)->firstOrFail();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertSame(500, $delivery->response_code);

        // Unsubscribed events don't dispatch at all.
        $this->assertSame(1, WebhookDelivery::where('webhook_id', $hook->id)->count());
    }
}
