<?php

namespace App\Http\Controllers;

use App\Actions\InstallMinistry;
use App\Models\MinistrySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Support\AddressCatalog;

class SetupWizardController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (blank(config('app.key'))) {
            return redirect('/install');
        }

        if (Schema::hasTable('ministry_settings') && MinistrySetting::query()->exists()) {
            return to_route('public.home');
        }

        return view('setup.wizard');
    }

    public function store(Request $request, InstallMinistry $installer, AddressCatalog $addresses): RedirectResponse
    {
        if (! Schema::hasTable('ministry_settings')) {
            return redirect('/install.php');
        }

        if (MinistrySetting::query()->exists()) {
            return to_route('login')->withErrors(['installation' => 'This installation is already configured.']);
        }

        $request->validate([
            'location' => ['required', 'string', 'max:160'],
            'location_address_line_1' => ['required', 'string', 'max:255'],
            'location_address_line_2' => ['nullable', 'string', 'max:255'],
            'location_city' => ['required', 'string', 'max:120'],
            'location_region_code' => ['nullable', 'string', 'max:16'],
            'location_postal_code' => ['nullable', 'string', 'max:32'],
            'location_country_code' => ['required', Rule::in(array_keys($addresses->countries()))],
            'theme_preset' => ['required', Rule::in(['neutral', 'tech_blue', 'warm'])],
            'chrome_mode' => ['required', Rule::in(['preset', 'custom'])],
            'chrome_color' => ['nullable', 'required_if:chrome_mode,custom', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($request->filled('location_region_code') && ! $addresses->subdivisionName(
            (string) $request->input('location_country_code'),
            (string) $request->input('location_region_code'),
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'location_region_code' => 'Choose a valid state, province, or region for the selected country.',
            ]);
        }

        $admin = $installer->execute($request->only([
            'name', 'domain', 'timezone', 'location', 'tagline', 'contact_email', 'theme_preset', 'chrome_mode', 'chrome_color',
            'location_address_line_1', 'location_address_line_2', 'location_city',
            'location_region_code', 'location_postal_code', 'location_country_code',
            'admin_name', 'admin_email', 'password',
        ]));

        Auth::login($admin);
        $request->session()->regenerate();

        return to_route('staff.overview')->with('status', 'Your ministry workspace is ready. Complete the launch checklist from Settings when you are ready.');
    }
}
