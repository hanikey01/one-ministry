<?php

namespace App\Http\Controllers;

use App\Services\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(): JsonResponse
    {
        return response()->json(null, 204);
    }

    public function login(Request $request, PermissionChecker $permissions): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $this->identity($request, $permissions)]);
    }

    public function me(Request $request, PermissionChecker $permissions): JsonResponse
    {
        return response()->json(['data' => $this->identity($request, $permissions)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    private function identity(Request $request, PermissionChecker $permissions): array
    {
        $user = $request->user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'permissions' => $permissions->installationPermissions($user),
        ];
    }
}
