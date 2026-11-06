<?php

namespace App\Http\Controllers;

use App\Actions\UpdateMinistrySettings;
use App\Models\MinistrySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->publicFields(MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID))]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:240'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'theme_preset' => ['sometimes', Rule::in(['neutral', 'tech_blue', 'warm'])],
            'chrome_mode' => ['sometimes', Rule::in(['preset', 'custom'])],
            'chrome_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'staff_font' => ['sometimes', Rule::in(['modern', 'editorial', 'humanist'])],
            'staff_density' => ['sometimes', Rule::in(['comfortable', 'compact'])],
        ]);

        $setting = app(UpdateMinistrySettings::class)->execute($validated, $request->user(), $request->ip());

        return response()->json(['data' => $this->publicFields($setting)]);
    }

    private function publicFields(MinistrySetting $setting): array
    {
        return $setting->only(['name', 'tagline', 'contact_email', 'theme_preset', 'chrome_mode', 'chrome_color', 'staff_font', 'staff_density']);
    }
}
