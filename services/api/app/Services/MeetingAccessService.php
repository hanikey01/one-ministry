<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MeetingAccessService
{
    public function __construct(private readonly ZoomVideoSdkService $zoom)
    {
    }

    /** @return array{status:string,participant_id:string,grant?:array{token:string,expires_at:string,session_name:string,session_passcode:string}} */
    public function join(array $meeting, User $user, string $displayName, ?string $ip): array
    {
        if (! in_array($meeting->status, ['scheduled', 'live'], true)) {
            throw ValidationException::withMessages(['meeting' => 'This meeting is not open for joining.']);
        }

        $participant = DB::table('meeting_participants')
            ->where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
        $isHost = $meeting->created_by === $user->id || in_array($participant?->role, ['host', 'moderator'], true);
        if ($meeting->access_policy === 'invitation_only' && ! $participant && ! $isHost) {
            abort(403, 'An invitation is required for this meeting.');
        }

        if (! $participant) {
            $participantId = (string) Str::uuid();
            $admission = $meeting->entry_policy === 'open_room' ? 'admitted' : 'waiting';
            DB::table('meeting_participants')->insert([
                'id' => $participantId, 'meeting_id' => $meeting->id, 'user_id' => $user->id,
                'display_name' => $displayName, 'role' => 'participant', 'admission_status' => $admission,
                'provider_identity' => substr((string) $user->id, 0, 36), 'access_token_hash' => hash('sha512', Str::random(48)),
                'expires_at' => now()->addHours(8), 'admitted_at' => $admission === 'admitted' ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $participant = DB::table('meeting_participants')->where('id', $participantId)->first();
        }

        if (! $isHost && $participant->admission_status !== 'admitted') {
            return ['status' => 'waiting', 'participant_id' => $participant->id];
        }

        $grant = $this->zoom->issueJoinGrant($meeting->session_name, $meeting->session_passcode_encrypted, $user, $displayName, $isHost);
        DB::table('meeting_participants')->where('id', $participant->id)->update(['joined_at' => now(), 'updated_at' => now()]);
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'actor_user_id' => $user->id, 'action' => 'meeting.zoom_grant_issued',
            'subject_type' => 'meeting', 'subject_id' => $meeting->id,
            'changes_json' => json_encode(['role' => $isHost ? 'host' : 'participant']), 'ip_address' => $ip, 'created_at' => now(),
        ]);

        return ['status' => 'admitted', 'participant_id' => $participant->id, 'grant' => $grant];
    }
}
