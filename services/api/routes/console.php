<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ministry:prune-playback-diagnostics')->dailyAt('03:20')->withoutOverlapping();
Schedule::command('ministry:probe-live-streams')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ministry:record-scheduler-heartbeat')->everyMinute()->withoutOverlapping();
