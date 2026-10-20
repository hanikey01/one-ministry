<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PermissionChecker
{
    public function allows(User $user, string $permission, string $scopeType = 'installation', ?string $scopeId = null): bool
    {
        return $this->activeGrants($user)
            ->where('role_permissions.permission_key', $permission)
            ->where(function ($query) use ($scopeType, $scopeId): void {
                $query->where(function ($query): void {
                    $query->where('user_role_assignments.scope_type', 'installation')
                        ->whereNull('user_role_assignments.scope_id');
                });

                if ($scopeType !== 'installation' && $scopeId !== null) {
                    $query->orWhere(function ($query) use ($scopeType, $scopeId): void {
                        $query->where('user_role_assignments.scope_type', $scopeType)
                            ->where('user_role_assignments.scope_id', $scopeId);
                    });
                }
            })
            ->exists();
    }

    /** @return array<int, string> */
    public function installationPermissions(User $user): array
    {
        return $this->activeGrants($user)
            ->where('user_role_assignments.scope_type', 'installation')
            ->whereNull('user_role_assignments.scope_id')
            ->distinct()
            ->orderBy('role_permissions.permission_key')
            ->pluck('role_permissions.permission_key')
            ->all();
    }

    private function activeGrants(User $user): Builder
    {
        return DB::table('user_role_assignments')
            ->join('role_permissions', 'role_permissions.role_key', '=', 'user_role_assignments.role_key')
            ->where('user_role_assignments.user_id', $user->id)
            ->whereNull('user_role_assignments.revoked_at')
            ->where(function ($query): void {
                $query->whereNull('user_role_assignments.expires_at')
                    ->orWhere('user_role_assignments.expires_at', '>', now());
            });
    }
}
