<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_page_generations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 80);
            $table->string('request_hash', 64);
            $table->string('state', 20)->default('queued');
            $table->longText('payload_encrypted');
            $table->unsignedSmallInteger('stage_attempt')->default(0);
            $table->timestamp('available_at');
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['actor_user_id', 'idempotency_key']);
            $table->index(['state', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_page_generations');
    }
};
