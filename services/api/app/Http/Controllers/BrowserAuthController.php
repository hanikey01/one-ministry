<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\PasswordRecoveryService;
use App\Services\MemberRegistrationVerificationService;
use App\Services\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrowserAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (! MinistrySetting::query()->exists()) {
            return to_route('setup.create');
        }

        return view('auth.login', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID)]);
    }

    public function registerCreate(): View|RedirectResponse
    {
        if (! MinistrySetting::query()->exists()) {
            return to_route('setup.create');
        }

        return view('auth.register', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID)]);
    }

    public function registerStore(Request $request, MemberRegistrationVerificationService $verification): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        ]);
        $email = Str::lower($data['email']);
        $id = $verification->request(trim($data['name']), $email);
        $request->session()->put('member_registration', ['id' => $id, 'name' => trim($data['name']), 'email' => $email]);
        return to_route('register.verify')->with('status', 'We sent a six-digit code to '.$email.'.');
    }

    public function registerVerifyCreate(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('member_registration');
        return $pending ? view('auth.register-verify', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'pending' => $pending]) : to_route('register');
    }

    public function registerVerifyStore(Request $request, MemberRegistrationVerificationService $verification): RedirectResponse
    {
        $pending = $request->session()->get('member_registration');
        if (! $pending) return to_route('register');
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $verification->verify($pending['id'], $pending['email'], $data['code']);
        $request->session()->put('member_registration.verified', true);
        return to_route('register.complete');
    }

    public function registerResend(Request $request, MemberRegistrationVerificationService $verification): RedirectResponse
    {
        $pending = $request->session()->get('member_registration');
        if (! $pending) return to_route('register');
        $id = $verification->request($pending['name'], $pending['email']);
        $request->session()->put('member_registration.id', $id);
        $request->session()->forget('member_registration.verified');
        return back()->with('status', 'A new code has been sent.');
    }

    public function registerCompleteCreate(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('member_registration');
        return ($pending && ($pending['verified'] ?? false)) ? view('auth.register-complete', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'pending' => $pending]) : to_route('register');
    }

    public function registerCompleteStore(Request $request, MemberRegistrationVerificationService $verification): RedirectResponse
    {
        $pending = $request->session()->get('member_registration');
        if (! $pending || ! ($pending['verified'] ?? false)) return to_route('register');
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed'], 'terms' => ['accepted']]);
        $user = $verification->complete($pending['id'], $pending['name'], $pending['email'], $data['password'], $request->ip());
        $request->session()->forget('member_registration');
        Auth::login($user);
        $request->session()->regenerate();
        return to_route('member.home')->with('status', 'Your verified member account is ready.');
    }

    public function forgotCreate(): View|RedirectResponse
    {
        if (! MinistrySetting::query()->exists()) {
            return to_route('setup.create');
        }

        return view('auth.forgot-password', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID)]);
    }

    public function forgotStore(Request $request, PasswordRecoveryService $recovery): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $recovery->request($data['email']);

        return to_route('password.forgot')->with('status', 'If an account exists for that address, a reset link has been sent.');
    }

    public function resetCreate(Request $request): View|RedirectResponse
    {
        if (! MinistrySetting::query()->exists()) {
            return to_route('setup.create');
        }

        return view('auth.reset-password', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'email' => $request->query('email'), 'token' => $request->query('token')]);
    }

    public function resetStore(Request $request, PasswordRecoveryService $recovery): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $recovery->reset($data['email'], $data['token'], $data['password'], $request->ip());

        return to_route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }
    public function store(Request $request, PermissionChecker $permissions): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();
        $destination = $permissions->allows($request->user(), 'settings.read') ? route('staff.overview') : route('member.home');

        return redirect()->intended($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'You have signed out.');
    }
}
