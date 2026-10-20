<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_template_id')->nullable()->constrained('service_templates')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('title', 160);
            $table->string('service_type', 24)->default('broadcast');
            $table->string('timezone', 64);
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->json('weekdays')->nullable();
            $table->unsignedSmallInteger('day_of_month')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('starts_at_local');
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'starts_on']);
        });
        Schema::table('service_occurrences', function (Blueprint $table): void {
            $table->foreignUuid('service_schedule_id')->nullable()->constrained('service_schedules')->nullOnDelete();
            $table->string('service_type', 24)->default('broadcast');
            $table->boolean('schedule_override')->default(false);
            $table->index(['service_schedule_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_occurrences', function (Blueprint $table): void {
            $table->dropIndex(['service_schedule_id', 'starts_at']);
            $table->dropConstrainedForeignId('service_schedule_id');
            $table->dropColumn(['service_type', 'schedule_override']);
        });
        Schema::dropIfExists('service_schedules');
    }
};
