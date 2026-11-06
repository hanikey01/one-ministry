<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_connection_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40);
            $table->string('name', 160)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('consent_to_contact')->default(false);
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service_occurrence_id', 'kind', 'created_at'], 'svc_response_occurrence_kind_created_idx');
            $table->unique(['service_occurrence_id', 'idempotency_key'], 'svc_response_occurrence_idempotency_unique');
        });

        Schema::create('service_prayer_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name', 160)->nullable();
            $table->string('contact_email', 254)->nullable();
            $table->text('prayer_text');
            $table->string('visibility', 32)->default('assigned_care_only');
            $table->boolean('consent_to_contact')->default(false);
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service_occurrence_id', 'visibility', 'created_at'], 'svc_prayer_occurrence_visibility_created_idx');
            $table->unique(['service_occurrence_id', 'idempotency_key'], 'svc_prayer_occurrence_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prayer_requests');
        Schema::dropIfExists('service_connection_responses');
    }
};
