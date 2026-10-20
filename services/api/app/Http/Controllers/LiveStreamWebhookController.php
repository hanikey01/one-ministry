<?php

namespace App\Http\Controllers;

use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LiveStreamWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, LiveStreamService $streams): JsonResponse
    {
        abort_unless(in_array($provider, ['youtube', 'vimeo', 'hls'], true), 404);
        $secret = (string) config('services.livestream.webhook_secret');
        abort_if($secret === '', 503, 'Livestream webhook verification is not configured.');
        $provided = (string) $request->header('X-Ministry-Signature');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $provided), 401);
        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:255'],
            'service_occurrence_id' => ['required', 'uuid'],
            'ingest_health' => ['required', Rule::in(['unknown', 'healthy', 'degraded', 'offline'])],
            'playback_health' => ['required', Rule::in(['unknown', 'healthy', 'degraded', 'offline'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        if (DB::table('live_stream_events')->where('source', $provider)->where('external_event_id', $data['event_id'])->exists()) {
            return response()->json(['data' => ['status' => 'duplicate']]);
        }
        $session = DB::table('live_sessions')->where('service_occurrence_id', $data['service_occurrence_id'])->where('provider', $provider)->first();
        abort_unless($session, 404);
        $updated = $streams->updateHealth($session, $data['ingest_health'], $data['playback_health'], $data['reason'] ?? null, null, $provider, $data['event_id']);

        return response()->json(['data' => ['status' => 'processed', 'ingest_health' => $updated->ingest_health, 'playback_health' => $updated->playback_health]]);
    }
}
