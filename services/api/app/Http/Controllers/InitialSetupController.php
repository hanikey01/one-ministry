<?php

namespace App\Http\Controllers;

use App\Actions\InstallMinistry;
use App\Models\MinistrySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InitialSetupController extends Controller
{
    public function store(Request $request, InstallMinistry $installer): JsonResponse
    {
        if (MinistrySetting::query()->exists()) {
            throw ValidationException::withMessages([
                'installation' => 'This installation is already configured.',
            ]);
        }

        $admin = $installer->execute($request->only([
            'name', 'domain', 'timezone', 'location', 'tagline', 'contact_email', 'theme_preset',
            'admin_name', 'admin_email', 'password',
        ]));

        return response()->json([
            'data' => [
                'status' => 'configured',
                'admin_email' => $admin->email,
            ],
        ], 201);
    }
}
