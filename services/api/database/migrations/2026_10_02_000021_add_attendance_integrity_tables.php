<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_viewer_aggregates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->timestamp('bucket_started_at');
            $table->unsignedInteger('viewer_count');
            $table->string('source', 40)->default('broadcast_provider');
            $table->timestamps();
            $table->unique(['service_occurrence_id', 'bucket_started_at', 'source'], 'anon_viewer_occurrence_bucket_source_unique');
        });
        Schema::create('presence_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->restrictOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->index(['service_occurrence_id', 'person_id']);
        });
        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attendance_record_id')->constrained('attendance_records')->restrictOnDelete();
            $table->foreignUuid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['attendance_record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('presence_sessions');
        Schema::dropIfExists('anonymous_viewer_aggregates');
    }
};
