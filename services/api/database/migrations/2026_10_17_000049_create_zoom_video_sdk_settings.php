<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_video_sdk_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('sdk_key', 255)->nullable();
            $table->text('sdk_secret_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            $table->unsignedInteger('monthly_participant_minute_warning')->nullable();
            $table->unsignedInteger('monthly_participant_minute_limit')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('zoom_video_sdk_settings'); }
};
