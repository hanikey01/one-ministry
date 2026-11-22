<?php

namespace App\Http\Controllers;

use App\Support\AddressCatalog;
use Illuminate\Http\JsonResponse;

class AddressCatalogController extends Controller
{
    public function subdivisions(string $country, AddressCatalog $catalog): JsonResponse
    {
        $country = strtoupper($country);
        abort_unless(array_key_exists($country, $catalog->countries()), 404);

        $subdivisions = collect($catalog->subdivisions($country))
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'search_text' => $label.' '.$value,
            ])->values();

        return response()->json(['data' => [
            'country_code' => $country,
            'subdivisions' => $subdivisions,
        ]]);
    }
}
