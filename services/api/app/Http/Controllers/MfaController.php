<?php

namespace App\Http\Controllers;

use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function status(Request $request, MfaService $mfa): JsonResponse
    {
        return response()->json(['data' => ['enabled' => $mfa->enabled($request->user()), 'verified' => $this->verified($request)]]);
    }

    public function begin(Request $request, MfaService $mfa): JsonResponse
    {
        return response()->json(['data' => $mfa->begin($request->user())]);
    }

    public function enable(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $codes = $mfa->enable($request->user(), $data['code']);
        $request->session()->put('mfa_verified_at', now()->timestamp);

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }

    public function verify(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        if (! $mfa->verify($request->user(), $data['code'])) {
            throw ValidationException::withMessages(['code' => 'This verification code is invalid.']);
        } $request->session()->put('mfa_verified_at', now()->timestamp);

        return response()->json(['data' => ['status' => 'verified']]);
    }

    private function verified(Request $request): bool
    {
        return (int) $request->session()->get('mfa_verified_at', 0) > now()->subHours(12)->timestamp;
    }
}
