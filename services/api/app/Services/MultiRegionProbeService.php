<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MultiRegionProbeService
{
    public function __construct(private LiveStreamService $streams, private LiveStreamProviderRegistry $providers) {}

    public function run(object $session, string $trigger, ?User $actor = null): array
    {
        $runId = (string) Str::uuid();
        DB::table('live_stream_probe_runs')->insert(['id' => $runId, 'live_session_id' => $session->id, 'trigger' => $trigger, 'status' => 'running', 'created_by' => $actor?->id, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $regions = [['key' => 'origin', 'label' => 'Installation origin', 'type' => 'local'], ...$this->configuredRegions()];
        $results = collect($regions)->map(fn (array $region) => $this->probeRegion($runId, $session, $region))->all();
        $statuses = collect($results)->pluck('status');
        $overall = $statuses->every(fn ($status) => $status === 'healthy') ? 'healthy' : ($statuses->every(fn ($status) => $status === 'offline') ? 'offline' : 'degraded');
        DB::transaction(function () use ($runId, $session, $overall, $actor): void {
            DB::table('live_stream_probe_runs')->where('id', $runId)->update(['status' => $overall, 'completed_at' => now(), 'updated_at' => now()]);
            $current = DB::table('live_sessions')->where('id', $session->id)->first();
            $this->streams->updateHealth($current, $current->ingest_health, $overall, 'Regional playback probes completed.', $actor, 'regional_probe');
        });

        return $this->present($runId);
    }

    public function history(object $session): array
    {
        return DB::table('live_stream_probe_runs')->where('live_session_id', $session->id)->latest('created_at')->limit(12)->get()->map(fn ($run) => $this->present($run->id))->all();
    }

    public function configuredRegionSummary(): array
    {
        return collect([['key' => 'origin', 'label' => 'Installation origin', 'type' => 'local'], ...$this->configuredRegions()])->map(fn ($region) => ['key' => $region['key'], 'label' => $region['label'], 'type' => $region['type']])->all();
    }

    private function probeRegion(string $runId, object $session, array $region): array
    {
        $started = hrtime(true);
        $status = 'offline';
        $httpStatus = null;
        $error = null;
        try {
            if ($region['type'] === 'local') {
                $result = $this->streams->performProbe($session);
            } else {
                $this->streams->assertProbeTarget($region['endpoint']);
                $response = Http::timeout(12)->connectTimeout(4)->acceptJson()->withToken($region['token'])->post($region['endpoint'], ['target_url' => $this->providers->playbackUrl($session), 'playback_type' => $session->playback_type]);
                $response->throw();
                $result = Validator::make($response->json() ?: [], ['status' => 'required|in:healthy,offline', 'http_status' => 'nullable|integer|min:100|max:599', 'latency_ms' => 'required|integer|min:0|max:120000'])->validate();
            }
            $status = $result['status'];
            $httpStatus = $result['http_status'] ?? null;
        } catch (\Throwable $exception) {
            $error = match (true) {
                $exception instanceof ConnectionException => 'connection_failed', $exception instanceof ValidationException => 'invalid_response', default => 'probe_failed'
            };
        }
        $latency = isset($result['latency_ms']) ? (int) $result['latency_ms'] : max(1, (int) round((hrtime(true) - $started) / 1_000_000));
        $row = ['id' => (string) Str::uuid(), 'probe_run_id' => $runId, 'region_key' => $region['key'], 'region_label' => $region['label'], 'probe_type' => $region['type'], 'status' => $status, 'http_status' => $httpStatus, 'latency_ms' => $latency, 'error_code' => $error, 'created_at' => now()];
        DB::table('live_stream_probe_results')->insert($row);

        return $row;
    }

    private function configuredRegions(): array
    {
        return collect(config('ministry.livestream_probe_regions', []))->filter(fn ($region) => is_array($region) && isset($region['key'], $region['label'], $region['endpoint'], $region['token']))->map(fn ($region) => ['key' => Str::slug((string) $region['key'], '_'), 'label' => (string) $region['label'], 'endpoint' => (string) $region['endpoint'], 'token' => (string) $region['token'], 'type' => 'remote'])->unique('key')->values()->all();
    }

    private function present(string $runId): array
    {
        $run = (array) DB::table('live_stream_probe_runs')->find($runId);
        $run['results'] = DB::table('live_stream_probe_results')->where('probe_run_id', $runId)->orderBy('region_label')->get();

        return $run;
    }
}
