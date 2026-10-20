<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberPrayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        if (! $person) {
            return response()->json(['data' => []]);
        }
        $items = DB::table('prayer_requests')->where('person_id', $person->id)->latest()->get()->map(fn ($item) => [
            'id' => $item->id, 'prayer_text' => Crypt::decryptString($item->prayer_text_encrypted),
            'visibility' => $item->visibility, 'consent_to_contact' => (bool) $item->consent_to_contact,
            'status' => $item->status, 'removal_requested_at' => $item->removal_requested_at, 'created_at' => $item->created_at,
        ]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['prayer_text' => ['required', 'string', 'max:4000'], 'visibility' => ['required', 'in:prayer_team,assigned_care_only'], 'consent_to_contact' => ['required', 'boolean']]);
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        abort_unless($person, 422, 'Complete your member profile before submitting a prayer request.');
        $id = (string) Str::uuid();
        DB::table('prayer_requests')->insert(['id' => $id, 'person_id' => $person->id, 'user_id' => $request->user()->id, 'prayer_text_encrypted' => Crypt::encryptString(trim($data['prayer_text'])), 'visibility' => $data['visibility'], 'consent_to_contact' => $data['consent_to_contact'], 'status' => 'received', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'received']], 201);
    }

    public function requestRemoval(Request $request, string $prayer): JsonResponse
    {
        $query = DB::table('prayer_requests')->where('id', $prayer)->where('user_id', $request->user()->id);
        abort_unless($query->exists(), 404);
        $query->update(['status' => 'removal_requested', 'removal_requested_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $prayer, 'status' => 'removal_requested']]);
    }
}
