<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CarePrayerQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_active_prayer_requests_are_visible_and_only_super_administrators_can_reassign_them(): void
    {
        $superAdministrator = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
        $superAdministrator->forceFill(['email_verified_at' => now()])->save();
        $careWorker = User::factory()->create(['email_verified_at' => now()]);
        DB::table('user_role_assignments')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $careWorker->id, 'role_key' => 'care_staff',
            'scope_type' => 'installation', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post('/member/prayer', [
            'first_name' => 'Grace', 'email' => 'grace@example.org', 'prayer_text' => 'Please pray for my family.',
            'visibility' => 'assigned_care_only', 'consent_to_contact' => '1',
        ])->assertRedirect();
        $prayer = DB::table('prayer_requests')->first();

        $this->actingAs($superAdministrator)->get('/staff/care')
            ->assertOk()
            ->assertSee('Please pray for my family.')
            ->assertSee('Unassigned');

        $this->actingAs($superAdministrator)
            ->post(route('staff.care.prayer-requests.claim', $prayer->id), ['assigned_to' => $careWorker->id])
            ->assertRedirect(route('staff.care'));
        $this->assertDatabaseHas('prayer_requests', ['id' => $prayer->id, 'assigned_to' => $careWorker->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('audit_events', ['action' => 'care.prayer_claimed', 'subject_id' => $prayer->id]);

        $otherCareWorker = User::factory()->create(['email_verified_at' => now()]);
        DB::table('user_role_assignments')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $otherCareWorker->id, 'role_key' => 'care_staff',
            'scope_type' => 'installation', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($otherCareWorker)->get('/staff/care')
            ->assertOk()
            ->assertSee('Please pray for my family.')
            ->assertSee($careWorker->name);
        $this->actingAs($otherCareWorker)
            ->post(route('staff.care.prayer-requests.claim', $prayer->id))
            ->assertNotFound();
    }

    public function test_care_staff_can_read_legacy_plain_text_prayer_requests(): void
    {
        $superAdministrator = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
        $superAdministrator->forceFill(['email_verified_at' => now()])->save();
        $personId = (string) Str::uuid();
        DB::table('people')->insert([
            'id' => $personId, 'first_name' => 'Legacy', 'last_name' => 'Member', 'email' => 'legacy@example.org',
            'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('prayer_requests')->insert([
            'id' => (string) Str::uuid(), 'person_id' => $personId, 'user_id' => $superAdministrator->id,
            'prayer_text_encrypted' => 'Please pray for restored health.', 'visibility' => 'assigned_care_only',
            'consent_to_contact' => false, 'status' => 'received', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($superAdministrator)->get('/staff/care')
            ->assertOk()
            ->assertSee('Please pray for restored health.');
    }
}
