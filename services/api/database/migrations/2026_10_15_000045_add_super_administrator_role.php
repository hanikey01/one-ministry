<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'key' => 'super_administrator', 'name' => 'Super Administrator', 'is_system' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (DB::table('permissions')->pluck('key') as $permission) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_key' => 'super_administrator', 'permission_key' => $permission,
            ]);
        }

        $initialAdministrators = DB::table('user_role_assignments')
            ->whereNull('revoked_at')
            ->where('scope_type', 'installation')
            ->whereNull('scope_id')
            ->whereIn('role_key', ['ministry_administrator', 'security_administrator'])
            ->groupBy('user_id')
            ->havingRaw('count(distinct role_key) = 2')
            ->pluck('user_id');
        foreach ($initialAdministrators as $userId) {
            DB::table('user_role_assignments')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'user_id' => $userId, 'role_key' => 'super_administrator',
                'scope_type' => 'installation', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('role_key', 'super_administrator')->delete();
        DB::table('roles')->where('key', 'super_administrator')->delete();
    }
};
