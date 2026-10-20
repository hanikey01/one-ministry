<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_capacity_rehearsals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->unsignedInteger('expected_peak_viewers');
            $table->unsignedSmallInteger('headroom_percent')->default(25);
            $table->unsignedInteger('required_test_viewers');
            $table->string('geography', 255);
            $table->text('provider_plan')->nullable();
            $table->unsignedInteger('target_startup_ms')->default(3000);
            $table->unsignedSmallInteger('target_rebuffer_rate_bps')->default(100);
            $table->unsignedSmallInteger('target_error_rate_bps')->default(100);
            $table->unsignedInteger('target_chat_latency_ms')->default(2000);
            $table->string('status', 20)->default('planned');
            $table->unsignedInteger('actual_peak_viewers')->nullable();
            $table->unsignedInteger('measured_p95_startup_ms')->nullable();
            $table->unsignedSmallInteger('measured_rebuffer_rate_bps')->nullable();
            $table->unsignedSmallInteger('measured_error_rate_bps')->nullable();
            $table->unsignedInteger('measured_chat_p95_ms')->nullable();
            $table->string('evidence_url', 1000)->nullable();
            $table->text('result_notes')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['service_occurrence_id', 'created_at'], 'capacity_rehearsal_occurrence_created_idx');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_capacity_rehearsals');
    }
};
