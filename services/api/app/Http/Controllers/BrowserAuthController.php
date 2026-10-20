<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Models\User;
use App\Services\PasswordRecoveryService;
use App\Services\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function registerStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'terms' => ['accepted'],
        ]);
        $email = Str::lower($data['email']);
        $user = DB::transaction(function () use ($data, $email, $request): User {
            $user = User::query()->create(['name' => trim($data['name']), 'email' => $email, 'password' => $data['password']]);
            $names = preg_split('/\s+/', trim($data['name']), 2);
            $person = DB::table('people')->whereNull('user_id')->where('email', $email)->first();
            if ($person) {
                DB::table('people')->where('id', $person->id)->update(['user_id' => $user->id, 'status' => 'member', 'updated_at' => now()]);
            } else {
                DB::table('people')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'first_name' => $names[0] ?: 'Member', 'last_name' => $names[1] ?? null, 'email' => $email, 'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $user->id, 'action' => 'auth.member_registered', 'subject_type' => 'users', 'subject_id' => $user->id, 'changes_json' => json_encode(['channel' => 'public_signup']), 'ip_address' => $request->ip(), 'created_at' => now()]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('member.home')->with('status', 'Your member account is ready. Complete your profile whenever you are ready.');
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
