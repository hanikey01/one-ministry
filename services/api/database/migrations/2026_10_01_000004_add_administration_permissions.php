<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'locations.read' => 'View ministry locations',
            'locations.manage' => 'Create and update ministry locations',
            'features.read' => 'View module states',
            'features.manage' => 'Change module states',
        ] as $key => $description) {
            DB::table('permissions')->insertOrIgnore([
                'key' => $key, 'description' => $description,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_key' => 'ministry_administrator', 'permission_key' => $key,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['locations.read', 'locations.manage', 'features.read', 'features.manage'] as $key) {
            DB::table('role_permissions')->where('permission_key', $key)->delete();
            DB::table('permissions')->where('key', $key)->delete();
        }
    }
};
