<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_update_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('channel', 20)->default('stable');
            $table->string('mode', 30)->default('manual');
            $table->string('current_version', 80)->default('dev');
            $table->string('available_version', 80)->nullable();
            $table->string('available_channel', 20)->nullable();
            $table->string('status', 30)->default('not_checked');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_update_settings');
    }
};
