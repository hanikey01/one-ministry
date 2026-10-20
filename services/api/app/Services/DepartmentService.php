<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    public const MEMBER = 'member';
    public const COORDINATOR = 'coordinator';
    public const EXECUTIVE = 'executive';

    public function __construct(private PermissionChecker $permissions) {}

    public function canSeeAny(User $user): bool
    {
        return $this->permissions->allows($user, 'departments.manage')
            || $this->managedDepartmentIds($user)->isNotEmpty();
    }

    public function canView(User $user, string $departmentId): bool
    {
        return $this->permissions->allows($user, 'departments.manage')
            || $this->managedDepartmentIds($user)->contains($departmentId);
    }

    public function canManageTeam(User $user, string $departmentId): bool
    {
        return $this->canView($user, $departmentId);
    }

    public function createDepartment(array $input, User $actor, ?string $ip): string
    {
        $this->requireAdministrator($actor);
        $id = (string) Str::uuid();
        DB::table('departments')->insert([
            'id' => $id, 'name' => $input['name'], 'slug' => Str::slug($input['name']),
            'description' => $input['description'] ?? null, 'status' => 'active',
            'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($actor, 'department.created', $id, ['name' => $input['name']], $ip);

        return $id;
    }

    public function addMember(string $departmentId, string $personId, string $role, User $actor, ?string $ip): void
    {
        $this->requireDepartment($departmentId);
        $this->requireTeamManager($actor, $departmentId);

        if (! in_array($role, [self::MEMBER, self::COORDINATOR, self::EXECUTIVE], true)) {
            throw ValidationException::withMessages(['role' => 'Choose a valid department role.']);
        }
        if (! $this->permissions->allows($actor, 'departments.manage') && $role !== self::MEMBER) {
            throw new AuthorizationException('Only a ministry administrator can appoint department coordinators or executives.');
        }

        DB::transaction(function () use ($departmentId, $personId, $role, $actor, $ip): void {
            $existing = DB::table('department_memberships')->where('department_id', $departmentId)->where('person_id', $personId)->lockForUpdate()->first();
            if ($existing && $existing->role === self::EXECUTIVE && ! $this->permissions->allows($actor, 'departments.manage')) {
                throw new AuthorizationException('Only a ministry administrator can change an executive assignment.');
            }

            DB::table('department_memberships')->updateOrInsert(
                ['department_id' => $departmentId, 'person_id' => $personId],
                ['id' => $existing?->id ?? (string) Str::uuid(), 'role' => $role, 'status' => 'active', 'granted_by' => $actor->id, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]
            );
            $this->audit($actor, 'department.membership_saved', $departmentId, ['person_id' => $personId, 'role' => $role], $ip);
        });
    }

    public function removeMember(string $departmentId, string $membershipId, User $actor, ?string $ip): void
    {
        $this->requireTeamManager($actor, $departmentId);
        $membership = DB::table('department_memberships')->where('id', $membershipId)->where('department_id', $departmentId)->first();
        if ($membership === null) {
            abort(404);
        }
        if (! $this->permissions->allows($actor, 'departments.manage') && $membership->role !== self::MEMBER) {
            throw new AuthorizationException('Only a ministry administrator can remove a department coordinator or executive.');
        }

        DB::table('department_memberships')->where('id', $membershipId)->delete();
        $this->audit($actor, 'department.membership_removed', $departmentId, ['person_id' => $membership->person_id, 'role' => $membership->role], $ip);
    }

    private function managedDepartmentIds(User $user)
    {
        return DB::table('department_memberships')
            ->join('people', 'people.id', '=', 'department_memberships.person_id')
            ->where('people.user_id', $user->id)
            ->where('department_memberships.status', 'active')
            ->whereIn('department_memberships.role', [self::COORDINATOR, self::EXECUTIVE])
            ->pluck('department_memberships.department_id');
    }

    private function requireAdministrator(User $user): void
    {
        if (! $this->permissions->allows($user, 'departments.manage')) {
            throw new AuthorizationException('Only a ministry administrator can create departments.');
        }
    }

    private function requireTeamManager(User $user, string $departmentId): void
    {
        if (! $this->canManageTeam($user, $departmentId)) {
            throw new AuthorizationException('You can manage only the departments assigned to you.');
        }
    }

    private function requireDepartment(string $departmentId): void
    {
        if (! DB::table('departments')->where('id', $departmentId)->where('status', 'active')->exists()) {
            abort(404);
        }
    }

    private function audit(User $actor, string $action, string $departmentId, array $changes, ?string $ip): void
    {
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => $action,
            'subject_type' => 'departments', 'subject_id' => $departmentId,
            'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now(),
        ]);
    }
}

