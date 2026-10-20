<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_notes', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $t->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('content');
            $t->timestamps();
            $t->index(['service_occurrence_id', 'created_at']);
        });
        Schema::create('service_prompts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $t->foreignUuid('run_sheet_item_id')->nullable()->constrained('service_run_sheet_items')->nullOnDelete();
            $t->string('message', 500);
            $t->unsignedInteger('offset_seconds')->default(0);
            $t->boolean('fired')->default(false);
            $t->timestamp('fired_at')->nullable();
            $t->timestamps();
            $t->index(['service_occurrence_id', 'fired']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prompts');
        Schema::dropIfExists('service_notes');
    }
};
