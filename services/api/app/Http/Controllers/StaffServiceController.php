<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use App\Services\ServiceLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ServiceOccurrence::query()->orderByDesc('starts_at')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'timezone' => ['required', 'timezone'], 'starts_at' => ['required', 'date'], 'location_id' => ['nullable', 'uuid', 'exists:locations,id'], 'manual_stream_override' => ['sometimes', 'boolean'], 'rehearsal_mode' => ['sometimes', 'boolean']]);

        return response()->json(['data' => ServiceOccurrence::create($data)], 201);
    }

    public function transition(Request $request, ServiceOccurrence $service, ServiceLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validate(['state' => ['required', Rule::in(['scheduled', 'ready', 'live', 'interrupted', 'ended', 'cancelled'])]]);

        return response()->json(['data' => $lifecycle->transition($service, $data['state'], $request->user(), $request->ip())]);
    }
}
