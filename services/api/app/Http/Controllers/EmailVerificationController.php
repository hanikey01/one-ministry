<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function request(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $verification->request($request->user());

        return response()->json(['data' => ['status' => 'requested']])->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $verification->verify($data['token'], $request->ip());

        return response()->json(['data' => ['status' => 'verified']])->header('Cache-Control', 'no-store');
    }
}
