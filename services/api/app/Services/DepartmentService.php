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
            'description' => $input['description'] ?? null, 'image_url'=>$input['image_url']??null, 'image_alt'=>$input['image_alt']??null, 'category'=>$input['category']??null, 'contact_email'=>$input['contact_email']??null, 'featured'=>(bool)($input['featured']??false), 'status' => 'active',
            'is_public' => (bool) ($input['is_public'] ?? true),
            'accepting_requests' => (bool) ($input['accepting_requests'] ?? false),
            'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($actor, 'department.created', $id, ['name' => $input['name']], $ip);

        return $id;
    }

    public function updateDepartment(string $departmentId, array $input, User $actor, ?string $ip): void
    {
        $this->requireAdministrator($actor);
        $this->requireDepartment($departmentId);
        $changes = [
            'name'=>$input['name'] ?? DB::table('departments')->where('id',$departmentId)->value('name'), 'slug'=>Str::slug($input['name'] ?? DB::table('departments')->where('id',$departmentId)->value('name')),
            'description' => $input['description'] ?? null,
            'image_url'=>$input['image_url']??null, 'image_alt'=>$input['image_alt']??null, 'category'=>$input['category']??null, 'contact_email'=>$input['contact_email']??null, 'featured'=>(bool)($input['featured']??false),
            'is_public' => (bool) ($input['is_public'] ?? false),
            'accepting_requests' => (bool) ($input['is_public'] ?? false) && (bool) ($input['accepting_requests'] ?? false),
        ];
        DB::table('departments')->where('id', $departmentId)->update([...$changes, 'updated_at' => now()]);
        $this->audit($actor, 'department.settings_updated', $departmentId, $changes, $ip);
    }

    public function requestMembership(string $departmentId, User $user, ?string $message, ?string $ip): void
    {
        $department = DB::table('departments')->where('id', $departmentId)->where('status', 'active')->where('is_public', true)->where('accepting_requests', true)->first();
        abort_unless($department, 404);
        $person = DB::table('people')->where('user_id', $user->id)->first();
        abort_unless($person, 422, 'Complete your member profile before requesting to join a department.');

        if (DB::table('department_memberships')->where(['department_id' => $departmentId, 'person_id' => $person->id, 'status' => 'active'])->exists()) {
            throw ValidationException::withMessages(['department' => 'You already belong to this department.']);
        }
        if (DB::table('department_join_requests')->where(['department_id' => $departmentId, 'person_id' => $person->id, 'status' => 'pending'])->exists()) {
            throw ValidationException::withMessages(['department' => 'Your request is already waiting for review.']);
        }

        $id = (string) Str::uuid();
        DB::table('department_join_requests')->insert([
            'id' => $id, 'department_id' => $departmentId, 'person_id' => $person->id,
            'message' => filled($message) ? trim($message) : null, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($user, 'department.join_requested', $departmentId, ['request_id' => $id], $ip);
    }

    public function reviewRequest(string $departmentId, string $requestId, string $status, User $actor, ?string $ip): void
    {
        $this->requireTeamManager($actor, $departmentId);
        if (! in_array($status, ['approved', 'declined'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose approved or declined.']);
        }

        DB::transaction(function () use ($departmentId, $requestId, $status, $actor, $ip): void {
            $request = DB::table('department_join_requests')->where('id', $requestId)->where('department_id', $departmentId)->lockForUpdate()->first();
            abort_unless($request, 404);
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => 'This request has already been reviewed.']);
            }
            DB::table('department_join_requests')->where('id', $requestId)->update([
                'status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            if ($status === 'approved' && $request->person_id) {
                $membership = DB::table('department_memberships')->where(['department_id' => $departmentId, 'person_id' => $request->person_id])->first();
                DB::table('department_memberships')->updateOrInsert(
                    ['department_id' => $departmentId, 'person_id' => $request->person_id],
                    ['id' => $membership?->id ?? (string) Str::uuid(), 'role' => self::MEMBER, 'status' => 'active', 'granted_by' => $actor->id, 'created_at' => $membership?->created_at ?? now(), 'updated_at' => now()]
                );
            }
            $this->audit($actor, 'department.join_reviewed', $departmentId, ['request_id' => $requestId, 'status' => $status], $ip);
        });
    }

    public function withdrawRequest(string $departmentId, User $user, ?string $ip): void
    {
        $personId = DB::table('people')->where('user_id', $user->id)->value('id');
        $updated = DB::table('department_join_requests')->where(['department_id'=>$departmentId,'person_id'=>$personId,'status'=>'pending'])->update(['status'=>'withdrawn','reviewed_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated === 1, 404);
        $this->audit($user, 'department.join_withdrawn', $departmentId, [], $ip);
    }

    public function leave(string $departmentId, User $user, ?string $ip): void
    {
        $personId = DB::table('people')->where('user_id', $user->id)->value('id');
        $membership = DB::table('department_memberships')->where(['department_id'=>$departmentId,'person_id'=>$personId,'status'=>'active'])->first();
        abort_unless($membership, 404);
        if ($membership->role !== self::MEMBER) throw ValidationException::withMessages(['department'=>'Department leaders must be reassigned by an administrator before leaving.']);
        DB::table('department_memberships')->where('id', $membership->id)->delete();
        $this->audit($user, 'department.left', $departmentId, [], $ip);
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

