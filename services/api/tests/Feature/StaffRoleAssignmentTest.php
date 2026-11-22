<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function install(): User
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->enableMfa($admin);

        return $admin;
    }

    private function enableMfa(User $user): void
    {
        DB::table('mfa_credentials')->insert(['user_id' => $user->id, 'secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'), 'recovery_code_hashes' => '[]', 'enabled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function actingAsSensitive(User $user): void
    {
        $this->actingAs($user)->withSession(['mfa_verified_at' => now()->timestamp]);
    }

    public function test_staff_access_lists_each_account_once_with_a_multi_role_popup(): void
    {
        $admin = $this->install();
        $target = User::factory()->create(['name' => 'Existing Member', 'email' => 'member@example.org']);
        $this->actingAsSensitive($admin);
        $this->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['content_media_editor', 'care_staff']]);

        $response = $this->get('/staff/access')->assertOk()->assertSee('Super Administrator')->assertSee('Service &amp; Production Team', false)->assertSee('Children’s Team')->assertSee('staff-role-dialog-options', false)->assertSee('Manage roles');
        $response->assertSee('data-workspace-dialog-open="account-roles-'.$target->id.'"', false)->assertSee('id="account-roles-'.$target->id.'"', false)->assertDontSee('data-role-multiselect', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-workspace-dialog-open="account-roles-'.$target->id.'"'));
    }

    public function test_authorized_staff_can_atomically_add_and_remove_multiple_roles(): void
    {
        $admin = $this->install();
        $target = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAsSensitive($admin);

        $this->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['service_production_team', 'care_staff']])->assertRedirect(route('staff.access'));
        $serviceAssignment = DB::table('user_role_assignments')->where('user_id', $target->id)->where('role_key', 'service_production_team')->first();
        $this->assertNotNull($serviceAssignment);
        $this->assertDatabaseHas('user_role_assignments', ['user_id' => $target->id, 'role_key' => 'care_staff', 'revoked_at' => null]);

        $this->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['care_staff', 'content_media_editor']])->assertRedirect(route('staff.access'));
        $this->assertDatabaseHas('user_role_assignments', ['id' => $serviceAssignment->id, 'role_key' => 'service_production_team']);
        $this->assertNotNull(DB::table('user_role_assignments')->where('id', $serviceAssignment->id)->value('revoked_at'));
        $this->assertDatabaseHas('user_role_assignments', ['user_id' => $target->id, 'role_key' => 'content_media_editor', 'revoked_at' => null]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user_role_assignment.revoked', 'subject_id' => $serviceAssignment->id]);
    }

    public function test_empty_selection_removes_all_non_protected_roles(): void
    {
        $admin = $this->install();
        $target = User::factory()->create();
        $this->actingAsSensitive($admin);
        $this->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['content_media_editor']]);

        $this->put("/staff/access/accounts/{$target->id}/roles", [])->assertRedirect(route('staff.access'));

        $this->assertSame(0, DB::table('user_role_assignments')->where('user_id', $target->id)->whereNull('revoked_at')->count());
    }

    public function test_role_sync_requires_actor_mfa_and_target_security_readiness(): void
    {
        $admin = $this->install();
        $target = User::factory()->create();

        $this->actingAs($admin)->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['content_media_editor']])->assertRedirect(route('staff.security'));
        $this->actingAsSensitive($admin);
        $this->from('/staff/access')->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['super_administrator', 'security_administrator']])->assertRedirect('/staff/access')->assertSessionHasErrors('roles');
        $this->assertDatabaseMissing('user_role_assignments', ['user_id' => $target->id, 'role_key' => 'super_administrator', 'revoked_at' => null]);
    }

    public function test_final_super_administrator_cannot_be_removed_from_the_selected_set(): void
    {
        $admin = $this->install();
        $this->actingAsSensitive($admin);

        $this->from('/staff/access')->put("/staff/access/accounts/{$admin->id}/roles", ['roles' => ['ministry_administrator', 'security_administrator']])->assertRedirect('/staff/access')->assertSessionHasErrors('roles');
        $this->assertDatabaseHas('user_role_assignments', ['user_id' => $admin->id, 'role_key' => 'super_administrator', 'revoked_at' => null]);
    }

    public function test_unprivileged_user_cannot_sync_roles(): void
    {
        $this->install();
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $target = User::factory()->create();

        $this->actingAs($actor)->withSession(['mfa_verified_at' => now()->timestamp])->put("/staff/access/accounts/{$target->id}/roles", ['roles' => ['content_media_editor']])->assertForbidden();
    }
}
