<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'key' => 'content_editor', 'name' => 'Content Editor', 'is_system' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['content.manage' => 'Manage content drafts and recordings', 'content.publish' => 'Approve and publish content'] as $key => $description) {
            DB::table('permissions')->insertOrIgnore(['key' => $key, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'content_editor', 'permission_key' => $key]);
            if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
                DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => $key]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_key', ['content.manage', 'content.publish'])->delete();
        DB::table('permissions')->whereIn('key', ['content.manage', 'content.publish'])->delete();
        DB::table('roles')->where('key', 'content_editor')->delete();
    }
};
