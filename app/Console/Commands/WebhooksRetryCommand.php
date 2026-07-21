<?php

namespace App\Console\Commands;

use App\Domain\Webhooks\DeliverWebhookJob;
use App\Models\Site;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Re-dispatch pending webhook deliveries whose backoff window has elapsed. */
class WebhooksRetryCommand extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Retry pending webhook deliveries that are due';

    public function handle(): int
    {
        $sites = Site::withoutGlobalScopes()->where('status', 'active')->get();
        $count = 0;

        foreach ($sites as $site) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $site->tenant_id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

            $due = WebhookDelivery::where('site_id', $site->id)
                ->where('status', 'pending')
                ->whereNotNull('next_attempt_at')
                ->where('next_attempt_at', '<=', now())
                ->limit(100)
                ->get();

            foreach ($due as $delivery) {
                DeliverWebhookJob::dispatch($delivery->id, $site->tenant_id);
                $count++;
            }
        }

        $this->info("{$count} deliveries re-dispatched.");

        return self::SUCCESS;
    }
}
