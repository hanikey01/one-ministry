<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_polls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->string('question', 500);
            $table->string('status', 20)->default('draft');
            $table->boolean('anonymous')->default(true);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['service_occurrence_id', 'status']);
        });
        Schema::create('service_poll_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_poll_id')->constrained('service_polls')->cascadeOnDelete();
            $table->string('label', 240);
            $table->unsignedSmallInteger('position');
            $table->unique(['service_poll_id', 'position']);
        });
        Schema::create('service_poll_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_poll_id')->constrained('service_polls')->cascadeOnDelete();
            $table->foreignUuid('service_poll_option_id')->constrained('service_poll_options')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_key_hash', 128)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['service_poll_id', 'user_id']);
            $table->unique(['service_poll_id', 'guest_key_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_poll_responses');
        Schema::dropIfExists('service_poll_options');
        Schema::dropIfExists('service_polls');
    }
};
