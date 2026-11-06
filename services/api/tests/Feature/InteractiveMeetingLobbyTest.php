<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InteractiveMeetingLobbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiting_participant_is_not_granted_media_until_host_admits_them(): void
    {
        $host = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
        FeatureSetting::where('key', 'interactive_meetings')->update(['state' => 'enabled']);
        $host->forceFill(['email_verified_at' => now()])->save();
        DB::table('mfa_credentials')->insert([
            'user_id' => $host->id, 'secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'recovery_code_hashes' => json_encode([]), 'enabled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $meeting = (string) Str::uuid();
        DB::table('meetings')->insert([
            'id' => $meeting, 'created_by' => $host->id, 'title' => 'Prayer gathering', 'provider' => 'zoom_video_sdk',
            'session_name' => 'ministry-test-room', 'session_passcode_encrypted' => Crypt::encryptString('private-passcode'),
            'status' => 'live', 'access_policy' => 'member_only', 'entry_policy' => 'waiting_room',
            'participant_media_policy' => 'camera_mic_allowed', 'screen_share_policy' => 'presenter_host_only',
            'chat_policy' => 'enabled', 'recording_notice' => true, 'recording_policy' => 'off', 'breakouts_enabled' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $visitor = User::factory()->create();
        $this->actingAs($visitor);
        $participant = $this->postJson("/meetings/{$meeting}/join-grant", ['display_name' => 'Visitor'])->assertOk()
            ->assertJsonPath('data.status', 'waiting')->json('data.participant_id');
        $this->assertDatabaseHas('meeting_participants', ['id' => $participant, 'admission_status' => 'waiting', 'joined_at' => null]);
        $this->actingAs($host)->withSession(['mfa_verified_at' => now()->timestamp]);
        $this->getJson("/staff/meetings/{$meeting}/lobby")->assertOk()->assertJsonPath('data.0.id', $participant);

        $this->postJson("/staff/meetings/{$meeting}/lobby/{$participant}", ['decision' => 'admit'])
            ->assertOk()->assertJsonPath('data.admission_status', 'admitted');
        $this->getJson("/meetings/{$meeting}/waiting-status")->assertOk()->assertJsonPath('data.admission_status', 'admitted');
        $this->assertDatabaseHas('audit_events', ['action' => 'meeting.lobby_admitted', 'subject_id' => $participant]);
    }

    public function test_disabled_interactive_meetings_module_blocks_room_and_lobby_routes(): void
    {
        $host = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);

        $this->actingAs($host)->get('/meetings/'.(string) Str::uuid())->assertNotFound();
    }

    public function test_disabled_interactive_meetings_module_rejects_new_meeting_service_schedules(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);

        $this->actingAs($admin)->postJson('/api/v1/staff/service-schedule', [
            'title' => 'Prayer gathering', 'service_type' => 'meeting', 'timezone' => 'UTC', 'frequency' => 'weekly',
            'weekdays' => [0], 'starts_on' => now()->toDateString(), 'starts_at_local' => '10:00', 'duration_minutes' => 60,
        ])->assertUnprocessable();
    }
}
