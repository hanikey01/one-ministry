<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrayerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_prayer_requests_route_to_authorized_administrators_and_prayer_team_only(): void
    {
        $super = $this->install();
        $member = User::factory()->create();
        DB::table('people')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $member->id, 'first_name' => 'Grace', 'email' => $member->email,
            'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($member)->post('/member/prayer', [
            'prayer_text' => 'Please pray for wisdom and peace.', 'consent_to_contact' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('prayer_requests', ['visibility' => 'prayer_team', 'assigned_to' => null]);

        $prayerTeam = $this->staff('prayer_team');
        $administrator = $this->staff('ministry_administrator');
        $unrelated = $this->staff('content_media_editor');
        $careStaff = $this->staff('care_staff');

        foreach ([$super, $prayerTeam, $administrator] as $authorized) {
            $this->actingAs($authorized)->get('/staff/prayer-requests')->assertOk()->assertSee('Please pray for wisdom and peace.');
        }
        foreach ([$unrelated, $careStaff] as $denied) {
            $this->actingAs($denied)->get('/staff/prayer-requests')->assertForbidden();
        }
        $this->assertDatabaseHas('audit_events', ['action' => 'prayer.requests_read']);
    }

    public function test_assigned_care_visibility_is_rejected_by_the_member_api(): void
    {
        $this->install();
        $member = User::factory()->create();
        DB::table('people')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $member->id, 'first_name' => 'Grace', 'email' => $member->email,
            'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($member)->postJson('/api/v1/me/prayer-requests', [
            'prayer_text' => 'Please pray.', 'visibility' => 'assigned_care_only', 'consent_to_contact' => true,
        ])->assertCreated();
        $this->assertDatabaseHas('prayer_requests', ['visibility' => 'prayer_team']);
    }

    private function install(): User
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('user_role_assignments')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_key' => $role,
            'scope_type' => 'installation', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }
}
