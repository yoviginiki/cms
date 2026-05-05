<?php

namespace App\Domain\Publishing\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutoPublishService
{
    public function __construct(
        private PublishOrchestrator $orchestrator,
    ) {}

    /**
     * Trigger a publish via the queue worker (which has proper file permissions).
     * Always dispatches to queue — never writes files from the web process.
     */
    public function triggerIfEnabled(Site $site, ?User $user = null, string $changeType = 'full', ?string $changeId = null): void
    {
        if (!$this->isEnabled($site)) {
            return;
        }

        if (!$user) {
            $user = \Illuminate\Support\Facades\Auth::user();
        }
        if (!$user) {
            return;
        }

        try {
            $this->orchestrator->publish($site, $user, 'full');
            Log::info("Auto-publish queued for {$site->name} (trigger: {$changeType})");
        } catch (\RuntimeException $e) {
            // A deployment is already in progress — skip silently
            Log::debug("Auto-publish skipped: {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::warning("Auto-publish failed: {$e->getMessage()}");
        }
    }

    public function isEnabled(Site $site): bool
    {
        $settings = $site->settings ?? [];
        return ($settings['auto_publish'] ?? true) === true;
    }
}
