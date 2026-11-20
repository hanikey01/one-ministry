<?php

namespace App\Http\Controllers;

use App\Services\PlatformUpdateService;
use App\Models\MinistrySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PlatformUpdateController extends Controller
{
    public function index(Request $request, PlatformUpdateService $updates): View
    {
        return view('staff.updates', ['settings' => $updates->setting(), 'ministry' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'user' => $request->user()]);
    }

    public function update(Request $request, PlatformUpdateService $updates): RedirectResponse
    {
        $data = Validator::make($request->all(), ['channel' => ['required', 'in:stable,preview']])->validate();
        $updates->updateConfiguration($data, $request->user(), $request->ip());
        return to_route('staff.updates')->with('status', 'Platform update settings saved.');
    }

    public function check(Request $request, PlatformUpdateService $updates): RedirectResponse
    {
        $updates->check();
        return to_route('staff.updates')->with('status', 'Update availability checked.');
    }
}
