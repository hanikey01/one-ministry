<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RecordSchedulerHeartbeatCommand extends Command
{
    protected $signature = 'ministry:record-scheduler-heartbeat {--json : Output JSON}';

    protected $description = 'Record a scheduler heartbeat for shared-hosting health checks';

    public function handle(): int
    {
        $recordedAt = now()->toIso8601String();
        Storage::disk('local')->put('ministry-health/scheduler.json', json_encode(['recorded_at' => $recordedAt], JSON_THROW_ON_ERROR));

        $this->line($this->option('json') ? json_encode(['recorded_at' => $recordedAt], JSON_THROW_ON_ERROR) : "Scheduler heartbeat recorded at {$recordedAt}.");

        return self::SUCCESS;
    }
}
