<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')->insertOrIgnore([
            'role_key' => 'finance_staff',
            'permission_key' => 'giving.manage',
        ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('role_key', 'finance_staff')
            ->where('permission_key', 'giving.manage')
            ->delete();
    }
};
