<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (!config('cms.redis_enabled')) {
    Schedule::command('queue:work --max-jobs=10 --stop-when-empty')
        ->everyMinute()
        ->withoutOverlapping();
}

// Process scheduled content (pages/posts with future publish dates)
Schedule::call(new \App\Domain\Publishing\Jobs\ProcessScheduledContentJob())
    ->name('process-scheduled-content')
    ->everyMinute()
    ->withoutOverlapping();

// Clean up stale editor presence records
Schedule::call(function () {
    app(\App\Domain\Blocks\Services\EditorPresenceService::class)->cleanup();
})->name('editor-presence-cleanup')
  ->everyMinute();

// Scheduled URL imports (collections v3) — the command itself gates each
// collection on its hourly/daily cadence via settings.import_last_run.
Schedule::command('collections:fetch-imports')
    ->hourly()
    ->withoutOverlapping();

// Webhook delivery retries (collections v3)
Schedule::command('webhooks:retry')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Site Wizard workspace housekeeping (stale builds + leftover ZIP extracts)
Schedule::command('site-wizard:prune')
    ->daily()
    ->withoutOverlapping();
