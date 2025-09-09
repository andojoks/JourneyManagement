<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic booking queue processing every 5 minutes
Schedule::command('queue:process-scheduled --limit=50')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule cleanup of old queue items daily at 2 AM
Schedule::command('queue:process-bookings --cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping();
