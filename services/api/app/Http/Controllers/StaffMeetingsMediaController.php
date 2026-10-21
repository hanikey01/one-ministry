<?php

namespace App\Http\Controllers;

use App\Services\MeetingAccessService;
use App\Models\FeatureSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffMeetingsMediaController extends Controller
{
    public function meetings(): JsonResponse
    {
        $this->ensureMeetingsEnabled();
        return response()->json(['data' => DB::table('meetings')->latest()->get()]);
    }

    public function meeting(Request $r): JsonResponse
    {
        $this->ensureMeetingsEnabled();
        $d = $r->validate([
            'title' => ['required', 'string', 'max:200'], 'starts_at' => ['nullable', 'date'], 'recording_notice' => ['sometimes', 'boolean'],
            'access_policy' => ['sometimes', 'in:member_only,invitation_only'], 'entry_policy' => ['sometimes', 'in:open_room,waiting_room'],
            'participant_media_policy' => ['sometimes', 'in:camera_mic_allowed,view_only,host_presenter_only'],
            'screen_share_policy' => ['sometimes', 'in:all_participants,presenter_host_only,disabled'],
            'chat_policy' => ['sometimes', 'in:enabled,host_only,disabled'], 'recording_policy' => ['sometimes', 'in:off,host_start_with_notice,automatic_with_notice'],
            'breakouts_enabled' => ['sometimes', 'boolean'], 'declared_capacity' => ['nullable', 'integer', 'min:2', 'max:10000'],
        ]);
        $id = (string) Str::uuid();
        $sessionPasscode = Str::password(12, true, true, false, false);
        DB::table('meetings')->insert(array_merge($d, [
            'id' => $id, 'provider' => 'zoom_video_sdk', 'session_name' => 'ministry-'.substr(str_replace('-', '', $id), 0, 28),
            'session_passcode_encrypted' => Crypt::encryptString($sessionPasscode), 'created_by' => $r->user()->id,
            'recording_notice' => $r->boolean('recording_notice', true), 'breakouts_enabled' => $r->boolean('breakouts_enabled'),
            'created_at' => now(), 'updated_at' => now(),
        ]));
        DB::table('meeting_participants')->insert([
            'id' => (string) Str::uuid(), 'meeting_id' => $id, 'user_id' => $r->user()->id, 'display_name' => $r->user()->name,
            'role' => 'host', 'admission_status' => 'admitted', 'provider_identity' => substr((string) $r->user()->id, 0, 36),
            'access_token_hash' => hash('sha512', Str::random(48)), 'expires_at' => now()->addHours(8), 'admitted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['data' => DB::table('meetings')->where('id', $id)->first()], 201);
    }

    public function token(Request $r, string $meeting, MeetingAccessService $access): JsonResponse
    {
        $this->ensureMeetingsEnabled();
        $d = $r->validate(['display_name' => ['required', 'string', 'max:160']]);
        $record = DB::table('meetings')->where('id', $meeting)->where('provider', 'zoom_video_sdk')->first();
        abort_unless($record, 404);
        $result = $access->join($record, $r->user(), $d['display_name'], $r->ip());

        return response()->json(['data' => $result]);
    }

    private function ensureMeetingsEnabled(): void
    {
        abort_unless(app(\App\Services\ModuleAvailability::class)->visibleToStaff('interactive_meetings', auth()->user()), 404);
    }

    public function media(): JsonResponse
    {
        return response()->json(['data' => DB::table('media_assets')->where('status', 'published')->latest('published_at')->get()]);
    }

    public function addMedia(Request $r): JsonResponse
    {
        $d = $r->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'provider' => ['required', 'string', 'max:40'], 'provider_key' => ['required', 'string', 'max:255'], 'kind' => ['sometimes', 'string', 'max:30'], 'status' => ['sometimes', 'in:draft,published']]);
        $id = (string) Str::uuid();
        DB::table('media_assets')->insert(array_merge($d, ['id' => $id, 'published_at' => ($d['status'] ?? 'draft') === 'published' ? now() : null, 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['data' => DB::table('media_assets')->where('id', $id)->first()], 201);
    }
}
