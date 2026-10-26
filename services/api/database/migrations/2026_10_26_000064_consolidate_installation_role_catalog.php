<?php

use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $legacyRoleMap = [
        'site_editor' => 'content_media_editor',
        'content_editor' => 'content_media_editor',
        'finance_user' => 'finance_staff',
        'school_manager' => 'ministry_operations_coordinator',
    ];

    public function up(): void
    {
        $timestamp = now();

        foreach (RoleCatalog::INSTALLATION_ROLES as $key => $name) {
            DB::table('roles')->insertOrIgnore([
                'key' => $key,
                'name' => $name,
                'is_system' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            DB::table('roles')->where('key', $key)->update([
                'name' => $name,
                'is_system' => true,
                'updated_at' => $timestamp,
            ]);
        }

        foreach (RoleCatalog::PERMISSIONS as $role => $permissions) {
            foreach ($permissions as $permission) {
                if (DB::table('permissions')->where('key', $permission)->exists()) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_key' => $role,
                        'permission_key' => $permission,
                    ]);
                }
            }
        }

        foreach (DB::table('permissions')->pluck('key') as $permission) {
            if (! in_array($permission, ['audit.read', 'care.manage', 'care.notes.write', 'finance.view', 'finance.reconcile', 'roles.manage'], true)) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_key' => 'ministry_administrator',
                    'permission_key' => $permission,
                ]);
            }
        }

        // Existing ministry administrators and super administrators retain their
        // current complete grants; this migration consolidates only legacy staff
        // bundles and their outstanding invitations.
        foreach ($this->legacyRoleMap as $legacy => $replacement) {
            DB::table('user_role_assignments')->where('role_key', $legacy)->update([
                'role_key' => $replacement,
                'updated_at' => $timestamp,
            ]);
            DB::table('staff_invitations')->where('role_key', $legacy)->update([
                'role_key' => $replacement,
                'updated_at' => $timestamp,
            ]);
        }

        DB::table('role_permissions')->whereIn('role_key', array_keys($this->legacyRoleMap))->delete();
        DB::table('roles')->whereIn('key', array_keys($this->legacyRoleMap))->delete();
    }

    public function down(): void
    {
        // This is a pre-launch catalogue consolidation. Recreating removed
        // legacy roles on rollback would restore an ambiguous authorization
        // model, so rollback only removes grants that have no assignments.
        foreach (['service_production_team', 'childrens_team'] as $role) {
            if (! DB::table('user_role_assignments')->where('role_key', $role)->exists()
                && ! DB::table('staff_invitations')->where('role_key', $role)->exists()) {
                DB::table('role_permissions')->where('role_key', $role)->delete();
                DB::table('roles')->where('key', $role)->delete();
            }
        }
    }
};
