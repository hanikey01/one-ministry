<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StaffServiceResponseController extends Controller
{
    public function index(ServiceOccurrence $service): JsonResponse
    {
        $connections = DB::table('service_connection_responses')->where('service_occurrence_id', $service->id)->latest('created_at')->limit(100)->get(['id', 'kind', 'name', 'email', 'phone', 'consent_to_contact', 'created_at']);
        $prayerCount = DB::table('service_prayer_requests')->where('service_occurrence_id', $service->id)->count();

        return response()->json(['data' => ['service_id' => $service->id, 'connection_responses' => $connections, 'prayer_requests' => ['count' => $prayerCount, 'content_available' => false]]]);
    }
}
