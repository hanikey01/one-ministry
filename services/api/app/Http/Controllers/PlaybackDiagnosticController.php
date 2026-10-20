<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlaybackDiagnosticController extends Controller
{
    public function store(Request $request, ServiceOccurrence $service): JsonResponse
    {
        abort_unless(FeatureSetting::query()->where('key', 'digital_church')->value('state') === 'enabled', 404);
        abort_unless(! $service->rehearsal_mode && in_array($service->state, ['live', 'interrupted'], true), 404);
        $data = $request->validate([
            'event_id' => ['required', 'uuid'], 'session_key' => ['required', 'string', 'min:16', 'max:200'],
            'event_type' => ['required', Rule::in(['room_opened', 'player_loaded', 'playback_started', 'playback_stalled', 'playback_recovered', 'playback_failed', 'heartbeat', 'playback_ended'])],
            'playback_type' => ['nullable', Rule::in(['youtube', 'vimeo', 'hls'])],
            'quality' => ['nullable', Rule::in(['auto', 'standard', 'low'])],
            'startup_ms' => ['nullable', 'integer', 'min:0', 'max:300000'],
            'rebuffer_ms' => ['nullable', 'integer', 'min:0', 'max:300000'],
        ]);
        $inserted = DB::table('playback_diagnostic_events')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id,
            'client_event_id' => $data['event_id'], 'session_key_hash' => hash_hmac('sha256', $data['session_key'], (string) config('app.key')),
            'event_type' => $data['event_type'], 'playback_type' => $data['playback_type'] ?? null,
            'quality' => $data['quality'] ?? null, 'startup_ms' => $data['startup_ms'] ?? null,
            'rebuffer_ms' => $data['rebuffer_ms'] ?? null, 'created_at' => now(),
        ]);

        return response()->json(['data' => ['status' => $inserted ? 'recorded' : 'duplicate']], $inserted ? 201 : 200);
    }

    public function summary(ServiceOccurrence $service): JsonResponse
    {
        $since = now()->subHours(6);
        $query = DB::table('playback_diagnostic_events')->where('service_occurrence_id', $service->id)->where('created_at', '>=', $since);
        $counts = (clone $query)->selectRaw("COUNT(DISTINCT session_key_hash) AS viewer_sessions, SUM(CASE WHEN event_type = 'playback_started' THEN 1 ELSE 0 END) AS starts, SUM(CASE WHEN event_type = 'playback_stalled' THEN 1 ELSE 0 END) AS stalls, SUM(CASE WHEN event_type = 'playback_recovered' THEN 1 ELSE 0 END) AS recoveries, SUM(CASE WHEN event_type = 'playback_failed' THEN 1 ELSE 0 END) AS failures, COALESCE(ROUND(AVG(startup_ms)), 0) AS average_startup_ms")->first();
        $active = DB::table('playback_diagnostic_events')->where('service_occurrence_id', $service->id)->where('created_at', '>=', now()->subSeconds(45))->distinct('session_key_hash')->count('session_key_hash');
        $qualities = (clone $query)->whereNotNull('quality')->select('quality')->selectRaw('COUNT(*) AS events')->groupBy('quality')->orderBy('quality')->get();

        return response()->json(['data' => ['window_hours' => 6, 'active_recently' => $active, 'viewer_sessions' => (int) $counts->viewer_sessions, 'starts' => (int) $counts->starts, 'stalls' => (int) $counts->stalls, 'recoveries' => (int) $counts->recoveries, 'failures' => (int) $counts->failures, 'average_startup_ms' => (int) $counts->average_startup_ms, 'quality_events' => $qualities, 'definition' => 'Anonymous device sessions observed in the worship room. This is operational playback telemetry, not identified attendance.']]);
    }
}
