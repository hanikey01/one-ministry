<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InteractiveMeetingController extends Controller
{
    public function show(Request $request, string $meeting): View
    {
        $this->ensureEnabled();
        $room = DB::table('meetings')
            ->where('id', $meeting)
            ->where('provider', 'zoom_video_sdk')
            ->first(['id', 'created_by', 'title', 'status', 'starts_at', 'recording_notice', 'access_policy', 'entry_policy', 'participant_media_policy', 'screen_share_policy', 'chat_policy']);
        abort_unless($room, 404);

        $participant = DB::table('meeting_participants')->where('meeting_id', $room->id)->where('user_id', $request->user()->id)->first();
        abort_if($room->access_policy === 'invitation_only' && ! $participant && $room->created_by !== $request->user()->id, 403);
        abort_if($participant?->expires_at && now()->greaterThan($participant->expires_at), 403);
        $canManageLobby = $room->created_by === $request->user()->id || in_array($participant?->role, ['host', 'moderator'], true);

        return view('meetings.show', [
            'meeting' => $room,
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'canManageLobby' => $canManageLobby,
        ]);
    }

    public function waitingStatus(Request $request, string $meeting): JsonResponse
    {
        $this->ensureEnabled();
        $room = DB::table('meetings')->where('id', $meeting)->where('provider', 'zoom_video_sdk')->first(['id', 'status']);
        abort_unless($room, 404);
        $participant = DB::table('meeting_participants')->where('meeting_id', $meeting)->where('user_id', $request->user()->id)->first(['id', 'admission_status', 'expires_at']);
        abort_unless($participant, 403);
        abort_if($participant->expires_at && now()->greaterThan($participant->expires_at), 403);

        return response()->json(['data' => ['meeting_status' => $room->status, 'admission_status' => $participant->admission_status]]);
    }

    public function lobby(Request $request, string $meeting): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeHost($request, $meeting);

        return response()->json(['data' => DB::table('meeting_participants')
            ->where('meeting_id', $meeting)->where('admission_status', 'waiting')
            ->orderBy('created_at')->get(['id', 'display_name', 'created_at'])]);
    }

    public function decideLobby(Request $request, string $meeting, string $participant): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeHost($request, $meeting);
        $data = $request->validate(['decision' => ['required', 'in:admit,decline']]);
        $target = DB::table('meeting_participants')->where('id', $participant)->where('meeting_id', $meeting)->where('admission_status', 'waiting')->first();
        abort_unless($target, 404);
        $admitted = $data['decision'] === 'admit';

        DB::transaction(function () use ($request, $meeting, $target, $admitted): void {
            $updated = DB::table('meeting_participants')->where('id', $target->id)->where('admission_status', 'waiting')->update([
                'admission_status' => $admitted ? 'admitted' : 'declined',
                'admitted_at' => $admitted ? now() : null,
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['participant' => 'This lobby request has already been handled. Refresh the lobby and try again.']);
            }
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id,
                'action' => 'meeting.lobby_'.($admitted ? 'admitted' : 'declined'), 'subject_type' => 'meeting_participant',
                'subject_id' => $target->id, 'changes_json' => json_encode(['meeting_id' => $meeting]),
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });

        return response()->json(['data' => ['id' => $target->id, 'admission_status' => $admitted ? 'admitted' : 'declined']]);
    }

    private function authorizeHost(Request $request, string $meeting): object
    {
        $room = DB::table('meetings')->where('id', $meeting)->where('provider', 'zoom_video_sdk')->first(['id', 'created_by']);
        abort_unless($room, 404);
        $host = DB::table('meeting_participants')->where('meeting_id', $meeting)->where('user_id', $request->user()->id)->whereIn('role', ['host', 'moderator'])->exists();
        abort_unless($room->created_by === $request->user()->id || $host, 403);

        return $room;
    }

    private function ensureEnabled(): void
    {
        abort_unless(FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists(), 404);
    }
}
