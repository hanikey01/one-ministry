<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\AdministrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StaffLocationController extends Controller
{
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$required, 'string', 'max:160'],
            'timezone' => [$required, 'timezone'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Location::query()->orderByDesc('is_primary')->orderBy('name')->get()]);
    }

    public function store(Request $request, AdministrationService $administration): JsonResponse
    {
        $data = $request->validate($this->rules());
        $location = $administration->createLocation($data, $request->user(), $request->ip());

        return response()->json(['data' => $location], 201);
    }

    public function update(Request $request, Location $location, AdministrationService $administration): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $location = $administration->updateLocation($location, $data, $request->user(), $request->ip());

        return response()->json(['data' => $location]);
    }

    public function storePage(Request $request, AdministrationService $administration): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $administration->createLocation($data, $request->user(), $request->ip());

        return to_route('staff.locations')->with('status', 'Location created.');
    }

    public function updatePage(Request $request, Location $location, AdministrationService $administration): RedirectResponse
    {
        $data = $request->validate($this->rules(true));
        $administration->updateLocation($location, $data, $request->user(), $request->ip());

        return to_route('staff.locations')->with('status', 'Location updated.');
    }

    public function destroyPage(Request $request, Location $location, AdministrationService $administration): RedirectResponse
    {
        $inUse = DB::table('service_occurrences')->where('location_id', $location->id)->exists()
            || DB::table('service_schedules')->where('location_id', $location->id)->exists();
        if ($inUse) {
            throw new ConflictHttpException('This location is used by a service schedule and cannot be deleted.');
        }
        $administration->deleteLocation($location, $request->user(), $request->ip());

        return to_route('staff.locations')->with('status', 'Location deleted.');
    }
}
