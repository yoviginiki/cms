<?php

namespace App\Domain\Modules\Jobs;

use App\Domain\Modules\Services\ModuleRegistry;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Mixin for queued jobs that belong to a module. At handle-time the job asks
 * whether its module is still enabled; if not it NO-OPS (logs and returns)
 * rather than failing — a job enqueued while a module was on must not blow up
 * if the module is turned off before it runs.
 *
 * Usage inside handle():
 *
 *   if (! $this->moduleEnabledOrLog('culture-engine', $tenant)) {
 *       return;
 *   }
 */
trait GuardsModuleEnabled
{
    protected function moduleEnabledOrLog(string $key, ?Tenant $tenant = null): bool
    {
        $enabled = app(ModuleRegistry::class)->isEnabled($key, $tenant);

        if (!$enabled) {
            Log::info('Module job skipped: module disabled', [
                'module' => $key,
                'tenant_id' => $tenant?->getKey(),
                'job' => static::class,
            ]);
        }

        return $enabled;
    }
}
