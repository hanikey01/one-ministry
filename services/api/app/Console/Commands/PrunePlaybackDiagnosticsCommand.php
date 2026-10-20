<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrunePlaybackDiagnosticsCommand extends Command
{
    protected $signature = 'ministry:prune-playback-diagnostics {--days= : Override configured retention days} {--json : Output JSON}';

    protected $description = 'Delete expired anonymous playback diagnostic events';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('ministry.playback_diagnostic_retention_days', 7);
        if (filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1 || (int) $days > 365) {
            $this->error('Retention days must be an integer from 1 to 365.');

            return self::FAILURE;
        }
        $cutoff = now()->subDays((int) $days);
        $deleted = DB::table('playback_diagnostic_events')->where('created_at', '<', $cutoff)->delete();
        $result = ['deleted' => $deleted, 'retention_days' => (int) $days, 'cutoff' => $cutoff->toIso8601String()];
        $this->line($this->option('json') ? json_encode($result, JSON_THROW_ON_ERROR) : "Deleted {$deleted} expired playback diagnostic event(s); retained {$days} day(s).");

        return self::SUCCESS;
    }
}
