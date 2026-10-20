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

class DepartmentTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_can_serve_in_multiple_departments_and_a_coordinator_is_scoped(): void
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $coordinator = User::factory()->create();
        $member = User::factory()->create();
        $coordinatorPerson = $this->personFor($coordinator);
        $memberPerson = $this->personFor($member);
        $media = DB::table('departments')->where('slug', 'media-production')->value('id');
        $prayer = DB::table('departments')->where('slug', 'prayer')->value('id');
        $teams = app(DepartmentService::class);

        $teams->addMember($media, $coordinatorPerson, DepartmentService::COORDINATOR, $admin, null);
        $teams->addMember($media, $memberPerson, DepartmentService::MEMBER, $coordinator, null);
        $teams->addMember($prayer, $memberPerson, DepartmentService::MEMBER, $admin, null);

        $this->assertDatabaseHas('department_memberships', ['department_id' => $media, 'person_id' => $memberPerson, 'role' => DepartmentService::MEMBER]);
        $this->assertDatabaseHas('department_memberships', ['department_id' => $prayer, 'person_id' => $memberPerson, 'role' => DepartmentService::MEMBER]);
        $this->assertTrue($teams->canManageTeam($coordinator, $media));
        $this->assertFalse($teams->canManageTeam($coordinator, $prayer));
        $this->assertDatabaseHas('audit_events', ['action' => 'department.membership_saved', 'subject_id' => $media]);
    }

    public function test_a_coordinator_cannot_elevate_or_manage_another_department(): void
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $coordinator = User::factory()->create();
        $member = User::factory()->create();
        $coordinatorPerson = $this->personFor($coordinator);
        $memberPerson = $this->personFor($member);
        $media = DB::table('departments')->where('slug', 'media-production')->value('id');
        $prayer = DB::table('departments')->where('slug', 'prayer')->value('id');
        $teams = app(DepartmentService::class);
        $teams->addMember($media, $coordinatorPerson, DepartmentService::COORDINATOR, $admin, null);

        try {
            $teams->addMember($media, $memberPerson, DepartmentService::COORDINATOR, $coordinator, null);
            $this->fail('Coordinator elevation was allowed.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('department_memberships', ['department_id' => $media, 'person_id' => $memberPerson]);
        }

        $this->expectException(AuthorizationException::class);
        $teams->addMember($prayer, $memberPerson, DepartmentService::MEMBER, $coordinator, null);
    }

    private function personFor(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('people')->insert(['id' => $id, 'user_id' => $user->id, 'first_name' => $user->name, 'email' => $user->email, 'status' => 'member', 'consent_to_contact' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }
}
