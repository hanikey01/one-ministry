<?php

namespace App\Http\Controllers;

use App\Services\StaffInvitationService;
use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffInvitationController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.accept-invitation', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'token' => $request->query('token')]);
    }

    public function storeBrowser(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $invitations->accept($data['token'], $data['password']);
        return to_route('login')->with('status', 'Your staff account is ready. Sign in with your new password.');
    }

    public function index(): JsonResponse
    {
        $invitations = DB::table('staff_invitations')
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role_key', 'expires_at', 'created_at']);

        return response()->json(['data' => $invitations]);
    }

    public function store(Request $request, StaffInvitationService $invitations): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'role_key' => ['required', 'string', 'max:64'],
        ]);

        return response()->json(['data' => $invitations->create($data['name'], $data['email'], $data['role_key'], $request->user(), $request->ip())], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, string $id, StaffInvitationService $invitations): JsonResponse
    {
        if (! Str::isUuid($id) || ! $invitations->revoke($id, $request->user(), $request->ip())) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    public function accept(Request $request, StaffInvitationService $invitations): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $invitations->accept($data['token'], $data['password']);

        return response()->json(['data' => ['status' => 'accepted']], 201);
    }
}
