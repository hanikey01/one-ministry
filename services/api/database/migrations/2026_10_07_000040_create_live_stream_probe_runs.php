<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_probe_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->string('trigger', 20);
            $table->string('status', 20)->default('running');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['live_session_id', 'created_at']);
        });

        Schema::create('live_stream_probe_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('probe_run_id')->constrained('live_stream_probe_runs')->cascadeOnDelete();
            $table->string('region_key', 50);
            $table->string('region_label', 100);
            $table->string('probe_type', 20);
            $table->string('status', 20);
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('latency_ms');
            $table->string('error_code', 80)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['probe_run_id', 'region_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_probe_results');
        Schema::dropIfExists('live_stream_probe_runs');
    }
};
