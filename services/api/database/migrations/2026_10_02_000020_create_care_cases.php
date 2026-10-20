<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('prayer_request_id')->nullable()->unique();
            $table->foreign('prayer_request_id')->references('id')->on('service_prayer_requests')->nullOnDelete();
            $table->foreignUuid('assigned_to')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status']);
        });
        Schema::create('care_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('care_case_id')->constrained('care_cases')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('encrypted_body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['care_case_id', 'created_at']);
        });
        DB::table('roles')->insertOrIgnore([
            'key' => 'care_staff', 'name' => 'Care Staff', 'is_system' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['care.manage' => 'Manage assigned care cases', 'care.notes.write' => 'Write restricted care notes'] as $key => $description) {
            DB::table('permissions')->insertOrIgnore(['key' => $key, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'care_staff', 'permission_key' => $key]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_key', ['care.manage', 'care.notes.write'])->delete();
        DB::table('permissions')->whereIn('key', ['care.manage', 'care.notes.write'])->delete();
        DB::table('roles')->where('key', 'care_staff')->delete();
        Schema::dropIfExists('care_notes');
        Schema::dropIfExists('care_cases');
    }
};
