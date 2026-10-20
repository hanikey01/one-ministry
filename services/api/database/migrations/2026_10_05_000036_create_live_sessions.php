<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->unique()->constrained('service_occurrences')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('playback_type', 20);
            $table->string('provider_stream_id', 255)->nullable();
            $table->string('playback_url', 1000)->nullable();
            $table->string('lower_bandwidth_url', 1000)->nullable();
            $table->string('captions_url', 1000)->nullable();
            $table->string('status', 20)->default('configured')->index();
            $table->string('ingest_health', 20)->default('unknown');
            $table->string('playback_health', 20)->default('unknown');
            $table->text('fallback_message')->nullable();
            $table->timestampTz('last_health_at')->nullable();
            $table->string('last_probe_status', 20)->nullable();
            $table->unsignedInteger('last_probe_http_status')->nullable();
            $table->unsignedInteger('last_probe_latency_ms')->nullable();
            $table->timestampTz('last_probed_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('live_stream_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('source', 30)->default('operator');
            $table->string('external_event_id', 255)->nullable();
            $table->json('changes_json');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['source', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_events');
        Schema::dropIfExists('live_sessions');
    }
};
