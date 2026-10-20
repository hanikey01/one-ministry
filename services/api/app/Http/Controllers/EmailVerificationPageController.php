<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPageController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.verify-email', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'token' => (string) $request->query('token', '')]);
    }

    public function verify(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $verification->verify($data['token'], $request->ip());

        return to_route('login')->with('status', 'Your email is verified. You can now sign in and continue.');
    }
}
