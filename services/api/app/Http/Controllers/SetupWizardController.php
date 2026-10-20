<?php

namespace App\Http\Controllers;

use App\Actions\InstallMinistry;
use App\Models\MinistrySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetupWizardController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (MinistrySetting::query()->exists()) {
            return to_route('public.home');
        }

        return view('setup.wizard', ['timezones' => timezone_identifiers_list()]);
    }

    public function store(Request $request, InstallMinistry $installer): RedirectResponse
    {
        if (MinistrySetting::query()->exists()) {
            return to_route('login')->withErrors(['installation' => 'This installation is already configured.']);
        }

        $admin = $installer->execute($request->only([
            'name', 'domain', 'timezone', 'location', 'tagline', 'contact_email',
            'primary_color', 'accent_color', 'admin_name', 'admin_email', 'password',
        ]));

        Auth::login($admin);
        $request->session()->regenerate();

        return to_route('staff.overview')->with('status', 'Your ministry workspace is ready. Complete the launch checklist from Settings when you are ready.');
    }
}
