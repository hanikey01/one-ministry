<?php

namespace App\Http\Controllers;

use App\Services\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordRecoveryController extends Controller
{
    public function request(Request $request, PasswordRecoveryService $recovery): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $recovery->request($data['email']);

        return response()->json(['data' => ['status' => 'requested']])->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request, PasswordRecoveryService $recovery): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $recovery->reset($data['email'], $data['token'], $data['password'], $request->ip());

        return response()->json(['data' => ['status' => 'reset']])->header('Cache-Control', 'no-store');
    }
}
