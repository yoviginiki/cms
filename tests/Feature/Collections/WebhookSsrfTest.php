<?php

namespace Tests\Feature\Collections;

use App\Domain\Webhooks\DeliverWebhookJob;
use App\Models\Site;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\Http\OutboundHttpPolicy;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * F25 (audit 2026-09-22) — the webhook DNS guard checked only the A records
 * of the initial host: redirects were followed (public → private) and the
 * checked resolution was not the one the connection used. The outbound
 * policy now checks A+AAAA, refuses literal/private/reserved addresses,
 * pins the checked address for the connection and never follows redirects.
 */
class WebhookSsrfTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        config(['collections.import_skip_dns_guard' => false]);
    }

    private function policy(array $dns): OutboundHttpPolicy
    {
        return new OutboundHttpPolicy(fn (string $host) => $dns[$host] ?? []);
    }

    public function test_private_reserved_and_literal_destinations_are_refused(): void
    {
        $p = $this->policy([
            'public.test' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
            'internal.test' => ['10.0.0.5'],
            'mixed.test' => ['93.184.216.34', '192.168.1.1'],
            'v6local.test' => ['::1'],
            'v6mapped.test' => ['::ffff:127.0.0.1'],
            'metadata.test' => ['169.254.169.254'],
            'unresolvable.test' => [],
        ]);

        $this->assertNotNull($p->resolvePublic('https://public.test/hook'));

        foreach ([
            'http://public.test/hook',            // plain http
            'https://internal.test/hook',
            'https://mixed.test/hook',             // any private record poisons the host
            'https://v6local.test/hook',
            'https://v6mapped.test/hook',
            'https://metadata.test/hook',
            'https://unresolvable.test/hook',
            'https://127.0.0.1/hook',
            'https://[::1]/hook',
            'https://10.1.2.3:8443/hook',
            'https://user:pw@public.test/hook',
            'https://localhost/hook',
        ] as $url) {
            $this->assertNull($p->resolvePublic($url), "{$url} must be refused");
        }
    }

    public function test_connection_is_pinned_to_the_checked_address_and_never_redirects(): void
    {
        $p = $this->policy(['public.test' => ['93.184.216.34']]);
        $target = $p->resolvePublic('https://public.test:8443/hook');
        $opts = $p->guzzleOptions($target);

        $this->assertFalse($opts['allow_redirects']);
        $this->assertContains('public.test:8443:93.184.216.34', $opts['curl'][CURLOPT_RESOLVE]);
    }

    public function test_delivery_does_not_follow_a_redirect_and_records_a_failed_attempt(): void
    {
        Http::fake(['https://hooks.example.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        $this->app->instance(OutboundHttpPolicy::class, $this->policy(['hooks.example.com' => ['93.184.216.34']]));

        $hook = Webhook::create([
            'site_id' => $this->site->id, 'url' => 'https://hooks.example.com/receiver',
            'events' => ['record.created'], 'secret' => 'shh', 'active' => true,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_id' => $hook->id, 'site_id' => $this->site->id, 'event' => 'record.created',
            'payload' => ['x' => 1], 'status' => 'pending', 'attempts' => 0,
        ]);

        (new DeliverWebhookJob($delivery->id, $this->tenant->id))->handle();

        Http::assertSentCount(1);
        $fresh = $delivery->fresh();
        $this->assertNotSame('delivered', $fresh->status);
        $this->assertSame(1, $fresh->attempts);
    }

    public function test_private_host_is_never_contacted(): void
    {
        Http::fake();
        $this->app->instance(OutboundHttpPolicy::class, $this->policy(['hooks.example.com' => ['10.0.0.9']]));

        $hook = Webhook::create([
            'site_id' => $this->site->id, 'url' => 'https://hooks.example.com/receiver',
            'events' => ['record.created'], 'secret' => 'shh', 'active' => true,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_id' => $hook->id, 'site_id' => $this->site->id, 'event' => 'record.created',
            'payload' => ['x' => 1], 'status' => 'pending', 'attempts' => 0,
        ]);

        (new DeliverWebhookJob($delivery->id, $this->tenant->id))->handle();

        Http::assertNothingSent();
        $this->assertSame(1, $delivery->fresh()->attempts);
    }
}
