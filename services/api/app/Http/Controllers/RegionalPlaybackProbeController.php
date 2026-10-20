<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use App\Services\LiveStreamProviderRegistry;
use App\Services\MultiRegionProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegionalPlaybackProbeController extends Controller
{
    public function index(ServiceOccurrence $service, MultiRegionProbeService $probes, LiveStreamProviderRegistry $providers): JsonResponse
    {
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        if (! $session) {
            return response()->json(['data' => ['regions' => $probes->configuredRegionSummary(), 'provider_capabilities' => null, 'runs' => []]]);
        }

        return response()->json(['data' => ['regions' => $probes->configuredRegionSummary(), 'provider_capabilities' => $providers->capabilities($session->provider), 'runs' => $probes->history($session)]]);
    }

    public function run(Request $request, ServiceOccurrence $service, MultiRegionProbeService $probes): JsonResponse
    {
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        abort_unless($session, 404);

        return response()->json(['data' => $probes->run($session, 'manual', $request->user())], 201);
    }
}
