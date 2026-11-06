<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->boolean('email_marketing')->default(false);
            $table->boolean('sms_marketing')->default(false);
            $table->boolean('birthday_staff_reminder')->default(false);
            $table->boolean('birthday_public_recognition')->default(false);
            $table->string('quiet_hours_timezone', 64)->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();
            $table->unique('person_id');
        });
        DB::table('permissions')->insertOrIgnore([
            'key' => 'community.manage', 'description' => 'Manage events and community operations',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => 'community.manage']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'community.manage')->delete();
        DB::table('permissions')->where('key', 'community.manage')->delete();
        Schema::dropIfExists('notification_preferences');
    }
};
