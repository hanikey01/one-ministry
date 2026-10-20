<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_run_sheet_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->string('kind', 32)->default('segment');
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['service_occurrence_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_run_sheet_items');
    }
};
