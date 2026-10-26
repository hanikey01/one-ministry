<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffInvitationAndSessionTest extends TestCase
{
    use RefreshDatabase;

    private function install(): User
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org',
            'timezone' => 'Africa/Lagos', 'location' => 'Main Campus',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        DB::table('mfa_credentials')->insert(['user_id' => $admin->id, 'secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'), 'recovery_code_hashes' => '[]', 'enabled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    private function actingAsSensitive(User $user): void
    {
        $this->actingAs($user)->withSession(['mfa_verified_at' => now()->timestamp]);
    }

    public function test_invitation_is_one_time_and_grants_only_content_media_editor(): void
    {
        $admin = $this->install();
        $this->actingAsSensitive($admin);
        $response = $this->postJson('/api/v1/staff/invitations', [
            'name' => 'Eli Editor', 'email' => 'eli@example.org', 'role_key' => 'content_media_editor',
        ])->assertCreated();
        $url = $response->json('data.accept_url');
        $token = substr($url, strpos($url, 'token=') + 6);
        $this->assertDatabaseHas('staff_invitations', ['email' => 'eli@example.org', 'token_hash' => hash('sha256', $token)]);
        $this->assertStringNotContainsString($token, json_encode(DB::table('audit_events')->first(), JSON_THROW_ON_ERROR));
        $this->getJson('/api/v1/staff/invitations')->assertOk()->assertDontSee($token);

        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token, 'password' => 'editor-private-password',
            'password_confirmation' => 'editor-private-password',
        ])->assertCreated();
        $editor = User::query()->where('email', 'eli@example.org')->firstOrFail();
        $this->assertDatabaseHas('user_role_assignments', ['user_id' => $editor->id, 'role_key' => 'content_media_editor']);
        $this->actingAs($editor)->getJson('/api/v1/staff/locations')->assertForbidden();
        $this->getJson('/api/v1/staff/invitations')->assertForbidden();
        $this->getJson('/api/v1/staff/features')->assertForbidden();
        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token, 'password' => 'editor-private-password',
            'password_confirmation' => 'editor-private-password',
        ])->assertUnprocessable();
    }

    public function test_revoked_invitation_and_unprivileged_inviter_are_denied(): void
    {
        $admin = $this->install();
        $editor = User::factory()->create();
        $this->actingAs($editor)->postJson('/api/v1/staff/invitations', [
            'name' => 'Another', 'email' => 'another@example.org',
        ])->assertForbidden();
        $this->actingAsSensitive($admin);
        $response = $this->postJson('/api/v1/staff/invitations', [
            'name' => 'Another', 'email' => 'another@example.org', 'role_key' => 'content_media_editor',
        ])->assertCreated();
        $id = $response->json('data.id');
        $url = $response->json('data.accept_url');
        $token = substr($url, strpos($url, 'token=') + 6);
        $this->deleteJson("/api/v1/staff/invitations/{$id}")->assertNoContent();
        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token, 'password' => 'editor-private-password',
            'password_confirmation' => 'editor-private-password',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'another@example.org']);
    }

    public function test_scope_bound_roles_are_not_available_to_installation_wide_invitations(): void
    {
        $admin = $this->install();
        $this->actingAsSensitive($admin);

        $this->postJson('/api/v1/staff/invitations', [
            'name' => 'Production Lead',
            'email' => 'production@example.org',
            'role_key' => 'service_production_team',
        ])->assertUnprocessable()->assertJsonValidationErrors('role_key');

        $this->assertDatabaseMissing('staff_invitations', ['email' => 'production@example.org']);
    }

    public function test_session_handles_hide_cookie_values_and_enforce_ownership(): void
    {
        $admin = $this->install();
        $other = User::factory()->create();
        foreach ([[$admin->id, 'owned-session', '192.0.2.1'], [$other->id, 'foreign-session', '192.0.2.2']] as [$userId, $id, $ip]) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $userId, 'ip_address' => $ip,
                'payload' => '', 'last_activity' => time(),
            ]);
        }
        $this->actingAs($admin);
        $response = $this->getJson('/api/v1/auth/sessions')->assertOk();
        $sessions = collect($response->json('data'));
        $owned = $sessions->firstWhere('ip_address', '192.0.2.1');
        $this->assertNotNull($owned);
        $handle = $owned['id'];
        $this->assertNotSame('owned-session', $handle);
        $this->assertCount(0, $sessions->where('ip_address', '192.0.2.2'));
        $this->deleteJson("/api/v1/auth/sessions/{$handle}")->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => 'owned-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
        $foreignHandle = hash_hmac('sha256', 'foreign-session', config('app.key'));
        $this->deleteJson("/api/v1/auth/sessions/{$foreignHandle}")->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['action' => 'session.revoked']);
    }

    public function test_sign_out_everywhere_only_deletes_the_callers_sessions(): void
    {
        $admin = $this->install();
        $other = User::factory()->create();
        foreach ([[$admin->id, 'first-session'], [$admin->id, 'second-session'], [$other->id, 'foreign-session']] as [$userId, $id]) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $userId,
                'payload' => '', 'last_activity' => time(),
            ]);
        }
        $this->actingAs($admin)->deleteJson('/api/v1/auth/sessions')->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => 'first-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'second-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
        $this->assertDatabaseHas('audit_events', ['action' => 'sessions.revoked_all']);
    }
}
