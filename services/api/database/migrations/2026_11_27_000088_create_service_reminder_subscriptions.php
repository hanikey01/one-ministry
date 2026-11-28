<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_reminder_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignUuid('service_occurrence_id')->nullable()->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('service_schedule_id')->nullable()->constrained('service_schedules')->cascadeOnDelete();
            $table->string('channel', 20)->default('email');
            $table->unsignedSmallInteger('lead_minutes')->default(1440);
            $table->string('timezone', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'service_occurrence_id', 'channel', 'lead_minutes'], 'service_reminder_occurrence_unique');
            $table->unique(['person_id', 'service_schedule_id', 'channel', 'lead_minutes'], 'service_reminder_schedule_unique');
            $table->index(['active', 'channel']);
        });

        Schema::create('service_reminder_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('service_reminder_subscriptions')->cascadeOnDelete();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->timestamp('scheduled_for');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('failure_code', 120)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'service_occurrence_id'], 'service_reminder_delivery_unique');
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reminder_deliveries');
        Schema::dropIfExists('service_reminder_subscriptions');
    }
};
