<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use App\Support\MinistryTheme;
use Illuminate\Http\JsonResponse;

class PublicSiteController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settings = MinistrySetting::find(MinistrySetting::SINGLETON_ID);

        if ($settings === null) {
            return response()->json([
                'error' => [
                    'code' => 'installation_required',
                    'message' => 'This ministry has not completed setup.',
                    'fields' => (object) [],
                ],
                'request_id' => request()->headers->get('X-Request-ID'),
            ], 503);
        }

        $locations = Location::query()
            ->where('is_public', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'timezone', 'address', 'is_primary']);

        $features = FeatureSetting::query()
            ->pluck('state', 'key')
            ->map(fn (string $state): string => $state === 'enabled' ? 'enabled' : 'disabled');

        return response()->json(['data' => [
            'name' => $settings->name,
            'tagline' => $settings->tagline,
            'public_domain' => $settings->public_domain,
            'contact_email' => $settings->contact_email,
            'timezone' => $settings->timezone,
            'theme' => MinistryTheme::publicTheme($settings),
            'locations' => $locations,
            'features' => $features,
        ]]);
    }
}
