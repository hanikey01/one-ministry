<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\AdministrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\Support\AddressCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffLocationController extends Controller
{
    private function rules(AddressCatalog $addresses, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$required, 'string', 'max:160'],
            'timezone' => [$required, 'timezone'],
            'address' => ['nullable', 'string', 'max:2000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:120'],
            'region_code' => ['nullable', 'string', 'max:16'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', Rule::in(array_keys($addresses->countries()))],
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

    public function store(Request $request, AdministrationService $administration, AddressCatalog $addresses): JsonResponse
    {
        $data = $this->normalizeAddress($request->validate($this->rules($addresses)), null, $addresses);
        $location = $administration->createLocation($data, $request->user(), $request->ip());

        return response()->json(['data' => $location], 201);
    }

    public function update(Request $request, Location $location, AdministrationService $administration, AddressCatalog $addresses): JsonResponse
    {
        $data = $this->normalizeAddress($request->validate($this->rules($addresses, true)), $location, $addresses);
        $location = $administration->updateLocation($location, $data, $request->user(), $request->ip());

        return response()->json(['data' => $location]);
    }

    public function storePage(Request $request, AdministrationService $administration, AddressCatalog $addresses): RedirectResponse
    {
        $data = $this->normalizeAddress($request->validate($this->rules($addresses)), null, $addresses);
        $administration->createLocation($data, $request->user(), $request->ip());

        return to_route('staff.locations')->with('status', 'Location created.');
    }

    public function updatePage(Request $request, Location $location, AdministrationService $administration, AddressCatalog $addresses): RedirectResponse
    {
        $data = $this->normalizeAddress($request->validate($this->rules($addresses, true)), $location, $addresses);
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

    private function normalizeAddress(array $data, ?Location $location, AddressCatalog $addresses): array
    {
        $keys = ['address_line_1', 'address_line_2', 'locality', 'region_code', 'postal_code', 'country_code'];
        if (! collect($keys)->contains(fn (string $key): bool => array_key_exists($key, $data))) {
            return $data;
        }

        $address = collect($keys)->mapWithKeys(fn (string $key): array => [
            $key => array_key_exists($key, $data) ? $data[$key] : $location?->{$key},
        ])->all();
        $hasAddress = collect($address)->contains(fn ($value): bool => filled($value));
        if ($hasAddress && (blank($address['address_line_1']) || blank($address['locality']) || blank($address['country_code']))) {
            throw ValidationException::withMessages([
                'address_line_1' => 'Street address, city or locality, and country are required when adding location details.',
            ]);
        }

        $countryCode = filled($address['country_code']) ? strtoupper((string) $address['country_code']) : null;
        $regionCode = filled($address['region_code']) ? (string) $address['region_code'] : null;
        $regionName = $addresses->subdivisionName($countryCode, $regionCode);
        if ($regionCode && ! $regionName) {
            throw ValidationException::withMessages([
                'region_code' => 'Choose a valid state, province, or region for the selected country.',
            ]);
        }

        $normalized = [
            ...$address,
            'country_code' => $countryCode,
            'country_name' => $addresses->countryName($countryCode),
            'region_code' => $regionCode,
            'region_name' => $regionName,
        ];

        return [...$data, ...$normalized, 'address' => $hasAddress ? $addresses->format($normalized) : ($location?->address)];
    }
}
