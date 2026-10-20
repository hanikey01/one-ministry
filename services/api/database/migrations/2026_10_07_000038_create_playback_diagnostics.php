<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_diagnostic_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->uuid('client_event_id')->unique();
            $table->string('session_key_hash', 64);
            $table->string('event_type', 30);
            $table->string('playback_type', 20)->nullable();
            $table->string('quality', 20)->nullable();
            $table->unsignedInteger('startup_ms')->nullable();
            $table->unsignedInteger('rebuffer_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['service_occurrence_id', 'created_at'], 'playback_diag_occurrence_created_idx');
            $table->index(['service_occurrence_id', 'session_key_hash', 'created_at'], 'playback_diag_occurrence_session_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_diagnostic_events');
    }
};
