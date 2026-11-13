<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Services\AdministrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffFeatureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => FeatureSetting::query()->where('key', '!=', 'attendance')->orderBy('key')->get(['key', 'state'])]);
    }

    public function update(Request $request, string $key, AdministrationService $administration): JsonResponse
    {
        $data = $request->validate(['state' => ['required', Rule::in(['disabled', 'staff_preview', 'enabled'])]]);
        $feature = FeatureSetting::query()->findOrFail($key);
        $feature = $administration->changeFeature($feature, $data['state'], $request->user(), $request->ip());

        return response()->json(['data' => $feature->only(['key', 'state'])]);
    }
}
