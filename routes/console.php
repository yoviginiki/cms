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
