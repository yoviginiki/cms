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
