<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicServiceController extends Controller
{
    public function show(ServiceOccurrence $service, LiveStreamService $streams): JsonResponse
    {
        abort_unless(FeatureSetting::query()->where('key', 'digital_church')->value('state') === 'enabled', 404);
        abort_unless(! $service->rehearsal_mode && in_array($service->state, ['live', 'interrupted'], true), 404);

        $meeting = in_array($service->service_type, ['meeting', 'hybrid'], true)
            ? DB::table('meetings')->where('service_occurrence_id', $service->id)->whereIn('status', ['scheduled', 'live'])->first(['id', 'entry_policy'])
            : null;
        abort_if($service->service_type === 'meeting' && ! $meeting, 404);

        return response()->json(['data' => [
            'id' => $service->id,
            'title' => $service->title,
            'state' => $service->state,
            'starts_at' => $service->starts_at->toIso8601String(),
            'timezone' => $service->timezone,
            'experience' => $service->service_type,
            'media' => $streams->publicDescriptor($service),
            'interactive_room_available' => $meeting !== null,
            'chat_enabled' => $service->state === 'live',
        ]]);
    }
}
