<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'key' => 'giving.manage',
            'description' => 'Configure donation purposes, payment providers, and offline giving details',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['ministry_administrator', 'super_administrator'] as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_key' => $role,
                'permission_key' => 'giving.manage',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'giving.manage')->delete();
        DB::table('permissions')->where('key', 'giving.manage')->delete();
    }
};
