<?php

namespace App\Domain\Webhooks;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * One delivery attempt: signed POST (X-Cms-Signature = HMAC-SHA256 of the raw
 * body with the webhook secret). Failure schedules the next attempt with
 * exponential backoff; webhooks:retry re-dispatches when due.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public string $deliveryId,
        public string $tenantId,
    ) {
    }

    public function handle(): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'delivered' || !$delivery->webhook) {
            return;
        }

        $hook = $delivery->webhook;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $hook->secret);

        $code = null;
        try {
            if (!$this->hostAllowed($hook->url)) {
                throw new \RuntimeException('Webhook host resolves to a private address.');
            }
            $response = Http::timeout(10)->connectTimeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Cms-Event' => $delivery->event,
                    'X-Cms-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($hook->url);
            $code = $response->status();
            $ok = $response->successful();
        } catch (\Throwable $e) {
            $ok = false;
            logger()->info("webhook delivery {$delivery->id} attempt failed: {$e->getMessage()}");
        }

        $attempts = $delivery->attempts + 1;

        if ($ok) {
            $delivery->update(['status' => 'delivered', 'attempts' => $attempts, 'response_code' => $code, 'next_attempt_at' => null]);
            $hook->update(['last_delivered_at' => now(), 'last_status' => $code]);

            return;
        }

        $hook->update(['last_status' => $code]);
        if ($attempts >= WebhookDelivery::MAX_ATTEMPTS) {
            $delivery->update(['status' => 'failed', 'attempts' => $attempts, 'response_code' => $code, 'next_attempt_at' => null]);

            return;
        }

        // 5min, 10min, 20min, 40min
        $delivery->update([
            'attempts' => $attempts,
            'response_code' => $code,
            'next_attempt_at' => now()->addMinutes(5 * (2 ** ($attempts - 1))),
        ]);
    }

    private function hostAllowed(string $url): bool
    {
        if (config('collections.import_skip_dns_guard')) {
            return true; // tests
        }
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips = @gethostbynamel($host) ?: [];
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
