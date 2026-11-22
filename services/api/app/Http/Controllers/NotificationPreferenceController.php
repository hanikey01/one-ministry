<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request, string $person): JsonResponse
    {
        $data = $request->validate([
            'email_marketing' => ['sometimes', 'boolean'],
            'sms_marketing' => ['sometimes', 'boolean'],
            'quiet_hours_timezone' => ['nullable', 'timezone'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);
        abort_unless(DB::table('people')->where('id', $person)->exists(), 404);
        DB::table('notification_preferences')->updateOrInsert(
            ['person_id' => $person],
            [...$data, 'id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['data' => DB::table('notification_preferences')->where('person_id', $person)->first()]);
    }
}
