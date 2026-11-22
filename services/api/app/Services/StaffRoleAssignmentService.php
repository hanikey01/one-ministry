<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffRoleAssignmentService
{
    /** @param list<string> $roleKeys
     *  @return array{added:int,removed:int,total:int}
     */
    public function sync(User $target, array $roleKeys, User $actor, ?string $ip): array
    {
        $requested = array_values(array_unique($roleKeys));

        return DB::transaction(function () use ($target, $requested, $actor, $ip): array {
            foreach ($requested as $roleKey) $this->validateRoleKey($roleKey);

            $current = $this->activeAssignments()->where('user_id', $target->id)->lockForUpdate()->get(['id', 'role_key']);
            $currentKeys = $current->pluck('role_key')->all();
            $added = array_values(array_diff($requested, $currentKeys));
            $removed = array_values(array_diff($currentKeys, $requested));

            foreach ($added as $roleKey) $this->validateTargetReadiness($target, $roleKey);

            if (in_array('super_administrator', $removed, true)) {
                $remaining = $this->activeAssignments()->where('role_key', 'super_administrator')->lockForUpdate()->count() - 1;
                if ($remaining < 1) throw ValidationException::withMessages(['roles' => 'At least one active Super Administrator must remain.']);
            }

            $timestamp = now();
            foreach ($current->whereIn('role_key', $removed) as $assignment) {
                DB::table('user_role_assignments')->where('id', $assignment->id)->update(['revoked_at' => $timestamp, 'updated_at' => $timestamp]);
                $this->audit($actor, 'user_role_assignment.revoked', $assignment->id, ['user_id' => $target->id, 'role' => $assignment->role_key, 'scope_type' => 'installation'], $ip);
            }

            foreach ($added as $roleKey) {
                $id = (string) Str::uuid();
                DB::table('user_role_assignments')->insert(['id' => $id, 'user_id' => $target->id, 'role_key' => $roleKey, 'scope_type' => 'installation', 'scope_id' => null, 'granted_by' => $actor->id, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
                $this->audit($actor, 'user_role_assignment.created', $id, ['user_id' => $target->id, 'role' => $roleKey, 'scope_type' => 'installation'], $ip);
            }

            return ['added' => count($added), 'removed' => count($removed), 'total' => count($requested)];
        });
    }

    private function validateRoleKey(string $roleKey): void
    {
        if (! array_key_exists($roleKey, RoleCatalog::INSTALLATION_ROLES) || ! DB::table('roles')->where('key', $roleKey)->exists()) {
            throw ValidationException::withMessages(['roles' => 'Choose only available staff roles.']);
        }
    }

    private function validateTargetReadiness(User $target, string $roleKey): void
    {
        if (! in_array($roleKey, RoleCatalog::MFA_REQUIRED_ROLES, true)) return;
        if ($target->email_verified_at === null) throw ValidationException::withMessages(['roles' => 'Verify this account’s email before granting Super Administrator or Security Administrator.']);
        if (! DB::table('mfa_credentials')->where('user_id', $target->id)->whereNotNull('enabled_at')->exists()) {
            throw ValidationException::withMessages(['roles' => 'This account must enable two-factor authentication before receiving Super Administrator or Security Administrator.']);
        }
    }

    private function activeAssignments()
    {
        return DB::table('user_role_assignments')->where('scope_type', 'installation')->whereNull('scope_id')->whereNull('revoked_at')->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    private function audit(User $actor, string $action, string $subjectId, array $changes, ?string $ip): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => $action, 'subject_type' => 'user_role_assignments', 'subject_id' => $subjectId, 'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now()]);
    }
}
