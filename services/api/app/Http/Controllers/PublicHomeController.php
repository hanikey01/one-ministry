<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicHomeController extends Controller
{
    public function __invoke(LiveStreamService $streams): JsonResponse
    {
        if (FeatureSetting::query()->where('key', 'digital_church')->value('state') !== 'enabled') {
            return response()->json(['data' => ['lead_state' => 'carousel', 'service_occurrence_id' => null, 'service' => null]]);
        }
        $live = ServiceOccurrence::query()->where('rehearsal_mode', false)->where('state', 'live')->orderBy('starts_at')->get()->first(function (ServiceOccurrence $service) use ($streams): bool {
            if ($service->service_type === 'meeting') {
                return DB::table('meetings')->where('service_occurrence_id', $service->id)->whereIn('status', ['scheduled', 'live'])->exists();
            }

            return $service->manual_stream_override || $streams->usable($service);
        });
        if ($live !== null) {
            return $this->response('live', $live);
        }
        $interrupted = ServiceOccurrence::query()->where('rehearsal_mode', false)->where('state', 'interrupted')->orderBy('starts_at')->first();
        if ($interrupted) {
            return $this->response('interrupted', $interrupted);
        }
        $upcoming = ServiceOccurrence::query()->where('rehearsal_mode', false)->whereIn('state', ['scheduled', 'ready'])->where('starts_at', '>', now())->orderBy('starts_at')->first();

        return $upcoming ? $this->response('upcoming', $upcoming) : response()->json(['data' => ['lead_state' => 'carousel', 'service_occurrence_id' => null, 'service' => null]]);
    }

    private function response(string $state, ServiceOccurrence $service): JsonResponse
    {
        return response()->json(['data' => ['lead_state' => $state, 'service_occurrence_id' => $service->id, 'service' => ['title' => $service->title, 'starts_at' => $service->starts_at->toIso8601String(), 'timezone' => $service->timezone, 'experience' => $service->service_type]]]);
    }
}
