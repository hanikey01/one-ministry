<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private function install(): User
    {
        return app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org',
            'timezone' => 'Africa/Lagos', 'location' => 'Main Campus',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
    }

    public function test_staff_settings_require_an_authenticated_permission(): void
    {
        $admin = $this->install();
        $this->getJson('/api/v1/staff/settings')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/staff/settings')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/staff/settings')->assertOk()
            ->assertJsonPath('data.name', 'Hope Community');
    }

    public function test_staff_workspace_requires_the_same_server_side_permission(): void
    {
        $admin = $this->install();
        $this->get('/staff')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get('/staff')->assertForbidden();
        $this->actingAs($admin)->get('/staff')->assertOk()
            ->assertSee('Ministry workspace')
            ->assertSee('Good to see you, Ada.');
    }

    public function test_login_rotates_session_and_logout_removes_access(): void
    {
        $this->install();
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.org', 'password' => 'wrong'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.org', 'password' => 'long-private-password'])
            ->assertOk()->assertJsonPath('data.permissions.0', 'audit.read');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'ada@example.org');
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_branding_change_is_audited_and_publicly_visible(): void
    {
        $admin = $this->install();
        $this->actingAs($admin)->patchJson('/api/v1/staff/settings', [
            'tagline' => 'Welcome home', 'primary_color' => '#123ABC',
        ])->assertOk()->assertJsonPath('data.tagline', 'Welcome home');

        $this->assertDatabaseCount('audit_events', 1);
        $event = DB::table('audit_events')->first();
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame('ministry_settings.updated', $event->action);
        $this->getJson('/api/v1/public/site')->assertJsonPath('data.tagline', 'Welcome home');

        $this->patchJson('/api/v1/staff/settings', ['tagline' => 'Welcome home'])->assertOk();
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_scoped_or_revoked_grant_does_not_allow_installation_settings(): void
    {
        $this->install();
        $user = User::factory()->create();
        DB::table('user_role_assignments')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id,
            'role_key' => 'ministry_administrator', 'scope_type' => 'location',
            'scope_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($user)->getJson('/api/v1/staff/settings')->assertForbidden();
        DB::table('user_role_assignments')->where('user_id', $user->id)->update([
            'scope_type' => 'installation', 'scope_id' => null, 'revoked_at' => now(),
        ]);
        $this->getJson('/api/v1/staff/settings')->assertForbidden();
    }
}
