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
Schedule::command('ministry:send-email-campaigns --limit=50')->everyMinute()->withoutOverlapping();
Schedule::command('ministry:process-ai-pages --limit=1')->everyMinute()->withoutOverlapping();
Schedule::command('ministry:refresh-ai-model-catalogs')->dailyAt('04:10')->withoutOverlapping();
// Run hourly because installations may use any IANA time zone. The command
// computes the ministry-local date and its durable delivery key prevents repeats.
Schedule::command('birthdays:send-reminders --days=1')->hourly()->withoutOverlapping();
