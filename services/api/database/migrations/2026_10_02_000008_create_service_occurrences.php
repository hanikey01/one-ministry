<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('title', 160);
            $table->string('timezone', 64);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('state', 20)->default('draft');
            $table->boolean('manual_stream_override')->default(false);
            $table->timestamps();
            $table->index(['location_id', 'starts_at']);
            $table->index(['state', 'starts_at']);
        });
        DB::table('permissions')->insertOrIgnore(['key' => 'services.manage', 'description' => 'Create and operate service occurrences', 'created_at' => now(), 'updated_at' => now()]);
        if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => 'services.manage']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'services.manage')->delete();
        DB::table('permissions')->where('key', 'services.manage')->delete();
        Schema::dropIfExists('service_occurrences');
    }
};
