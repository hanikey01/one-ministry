<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'key' => 'reports.view', 'description' => 'View ministry operational reports',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => 'reports.view']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'reports.view')->delete();
        DB::table('permissions')->where('key', 'reports.view')->delete();
    }
};
