<?php

namespace App\Console\Commands;

use App\Services\MultiRegionProbeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProbeLiveStreamsCommand extends Command
{
    protected $signature = 'ministry:probe-live-streams {--json : Output JSON}';

    protected $description = 'Run configured regional playback probes for active and soon-ready livestreams';

    public function handle(MultiRegionProbeService $probes): int
    {
        $sessions = DB::table('live_sessions')->join('service_occurrences', 'service_occurrences.id', '=', 'live_sessions.service_occurrence_id')
            ->whereIn('service_occurrences.state', ['ready', 'live', 'interrupted'])
            ->select('live_sessions.*')->get();
        $results = $sessions->map(fn ($session) => $probes->run($session, 'scheduled'))->all();
        $summary = ['sessions_probed' => count($results), 'runs' => collect($results)->map(fn ($run) => ['id' => $run['id'], 'status' => $run['status']])->all()];
        $this->line($this->option('json') ? json_encode($summary, JSON_THROW_ON_ERROR) : "Completed regional probes for {$summary['sessions_probed']} livestream session(s).");

        return self::SUCCESS;
    }
}
