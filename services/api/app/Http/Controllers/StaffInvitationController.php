<?php

namespace App\Http\Controllers;

use App\Services\StaffInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffInvitationController extends Controller
{
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
