<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->string('name', 160);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->string('key', 120)->primary();
            $table->string('description', 240);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->string('role_key', 80);
            $table->string('permission_key', 120);
            $table->primary(['role_key', 'permission_key']);
            $table->foreign('role_key')->references('key')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_key')->references('key')->on('permissions')->cascadeOnDelete();
        });

        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role_key', 80);
            $table->foreign('role_key')->references('key')->on('roles')->restrictOnDelete();
            $table->string('scope_type', 40)->default('installation');
            $table->uuid('scope_id')->nullable();
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'role_key', 'revoked_at']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
