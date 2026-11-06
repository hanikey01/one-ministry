<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use App\Services\DepartmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_request_join_and_use_discussion_only_after_approval(): void
    {
        $admin = $this->install();
        $departments = app(DepartmentService::class);
        $departmentId = $departments->createDepartment([
            'name' => 'Young Adults', 'description' => 'Community and service.',
            'is_public' => true, 'accepting_requests' => true, 'discussion_enabled' => true,
        ], $admin, '127.0.0.1');
        $member = $this->member('member@example.org');

        $this->actingAs($member)->get('/departments/young-adults')->assertOk();
        $this->post("/member/departments/{$departmentId}/join-request", ['message' => 'I would like to serve.'])->assertRedirect('/member/departments');
        $requestId = DB::table('department_join_requests')->where('department_id', $departmentId)->value('id');
        $this->assertNotNull($requestId);
        $this->post("/member/departments/{$departmentId}/join-request")->assertSessionHasErrors('department');
        $this->post("/member/departments/{$departmentId}/messages", ['content' => 'Hello'])->assertForbidden();

        $this->actingAs($admin)->patch("/staff/departments/{$departmentId}/join-requests/{$requestId}", ['status' => 'approved'])->assertRedirect();
        $this->actingAs($member)->post("/member/departments/{$departmentId}/messages", ['content' => 'Hello department'])->assertRedirect();
        $this->assertDatabaseHas('department_messages', ['department_id' => $departmentId, 'content' => 'Hello department']);
    }

    public function test_private_department_is_not_public_and_coordinator_cannot_change_public_settings(): void
    {
        $admin = $this->install();
        $departments = app(DepartmentService::class);
        $departmentId = $departments->createDepartment(['name' => 'Leadership', 'is_public' => false], $admin, null);
        $coordinator = $this->member('leader@example.org');
        $personId = DB::table('people')->where('user_id', $coordinator->id)->value('id');
        DB::table('department_memberships')->insert([
            'id' => (string) Str::uuid(), 'department_id' => $departmentId, 'person_id' => $personId,
            'role' => DepartmentService::COORDINATOR, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/departments/leadership')->assertNotFound();
        $this->actingAs($coordinator)->put("/staff/departments/{$departmentId}", ['is_public' => true])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $departments->updateDepartment($departmentId, ['is_public' => true], $coordinator, null);
    }

    private function install(): User
    {
        return app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main Campus',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
    }

    private function member(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        DB::table('people')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member',
            'email' => $email, 'status' => 'member', 'consent_to_contact' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }
}
