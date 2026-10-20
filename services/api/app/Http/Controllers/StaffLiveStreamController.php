<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffLiveStreamController extends Controller
{
    public function show(ServiceOccurrence $service): JsonResponse
    {
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        $events = $session ? DB::table('live_stream_events')->where('live_session_id', $session->id)->latest('created_at')->limit(20)->get() : [];

        return response()->json(['data' => ['session' => $session, 'events' => $events]]);
    }

    public function configure(Request $request, ServiceOccurrence $service, LiveStreamService $streams): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['youtube', 'vimeo', 'hls'])],
            'playback_url' => ['required', 'url', 'max:1000'],
            'lower_bandwidth_url' => ['nullable', 'url', 'max:1000'],
            'captions_url' => ['nullable', 'url', 'max:1000'],
            'fallback_message' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => $streams->configure($service, $data, $request->user())]);
    }

    public function health(Request $request, ServiceOccurrence $service, LiveStreamService $streams): JsonResponse
    {
        $data = $request->validate([
            'ingest_health' => ['required', Rule::in(['unknown', 'healthy', 'degraded', 'offline'])],
            'playback_health' => ['required', Rule::in(['unknown', 'healthy', 'degraded', 'offline'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        abort_unless($session, 404);
        $updated = $streams->updateHealth($session, $data['ingest_health'], $data['playback_health'], $data['reason'] ?? null, $request->user());

        return response()->json(['data' => $updated]);
    }

    public function probe(Request $request, ServiceOccurrence $service, LiveStreamService $streams): JsonResponse
    {
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        abort_unless($session, 404);
        $result = $streams->probe($session, $request->user());
        $session = DB::table('live_sessions')->where('id', $session->id)->first();
        if ($service->state === 'live' && $result['status'] === 'offline') {
            $streams->updateHealth($session, $session->ingest_health, 'offline', 'Automated playback probe failed.', $request->user(), 'probe');
        }

        return response()->json(['data' => $result]);
    }
}
