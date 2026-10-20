<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\AdministrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffLocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Location::query()->orderByDesc('is_primary')->orderBy('name')->get()]);
    }

    public function store(Request $request, AdministrationService $administration): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'timezone'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $location = $administration->createLocation($data, $request->user(), $request->ip());

        return response()->json(['data' => $location], 201);
    }

    public function update(Request $request, Location $location, AdministrationService $administration): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $location = $administration->updateLocation($location, $data, $request->user(), $request->ip());

        return response()->json(['data' => $location]);
    }
}
