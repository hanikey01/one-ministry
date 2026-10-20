<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email', 254);
            $table->string('name', 160);
            $table->string('role_key', 80);
            $table->foreign('role_key')->references('key')->on('roles')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->foreignUuid('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'accepted_at', 'revoked_at']);
        });

        DB::table('roles')->insertOrIgnore([
            'key' => 'site_editor', 'name' => 'Site Editor', 'is_system' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['settings.read', 'settings.update', 'locations.read', 'locations.manage'] as $permission) {
            if (DB::table('permissions')->where('key', $permission)->exists()) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_key' => 'site_editor', 'permission_key' => $permission,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        DB::table('role_permissions')->where('role_key', 'site_editor')->delete();
        DB::table('roles')->where('key', 'site_editor')->delete();
    }
};
