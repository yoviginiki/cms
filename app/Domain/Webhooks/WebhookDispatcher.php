<?php

namespace App\Domain\Webhooks;

use App\Models\Site;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

/**
 * Fans an event out to every active webhook on the site subscribed to it:
 * one pending delivery row per hook + a queued delivery job. Failures are
 * retried by webhooks:retry with exponential backoff (max 5 attempts).
 * Never throws — an unreachable endpoint must not break a record save.
 */
class WebhookDispatcher
{
    public function dispatch(Site $site, string $event, array $data): void
    {
        try {
            $hooks = Webhook::where('site_id', $site->id)
                ->where('active', true)
                ->get()
                ->filter(fn ($hook) => in_array($event, $hook->events ?? [], true));

            foreach ($hooks as $hook) {
                $delivery = WebhookDelivery::create([
                    'webhook_id' => $hook->id,
                    'site_id' => $site->id,
                    'event' => $event,
                    'payload' => [
                        'event' => $event,
                        'site' => ['id' => $site->id, 'slug' => $site->slug],
                        'occurred_at' => now()->toISOString(),
                        'data' => $data,
                    ],
                ]);

                DeliverWebhookJob::dispatch($delivery->id, $site->tenant_id);
            }
        } catch (\Throwable $e) {
            logger()->warning("webhook dispatch failed for {$event} on site {$site->id}: {$e->getMessage()}");
        }
    }

    /** Compact record payload shared by all record.* events. */
    public static function recordPayload(\App\Models\Record $record, \App\Models\ContentCollection $collection): array
    {
        return [
            'record' => [
                'id' => $record->id,
                'collection' => $collection->slug,
                'slug' => $record->slug,
                'title' => $record->title,
                'status' => $record->status,
                'data' => $record->data ?: (object) [],
            ],
        ];
    }
}
