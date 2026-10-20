<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('first_name', 100);
            $t->string('last_name', 100)->nullable();
            $t->string('email', 254)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('status', 24)->default('visitor');
            $t->boolean('consent_to_contact')->default(false);
            $t->timestamp('birthday')->nullable();
            $t->timestamps();
            $t->index(['email', 'phone']);
        });
        Schema::create('attendance_records', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $t->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $t->string('source', 32)->default('identified_presence');
            $t->string('status', 20)->default('present');
            $t->timestamp('observed_at')->useCurrent();
            $t->foreignUuid('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('correction_reason')->nullable();
            $t->timestamps();
            $t->unique(['service_occurrence_id', 'person_id']);
        });
        Schema::create('follow_up_tasks', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $t->foreignUuid('service_occurrence_id')->nullable()->constrained('service_occurrences')->nullOnDelete();
            $t->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind', 40);
            $t->string('status', 20)->default('open');
            $t->text('summary');
            $t->timestamp('due_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_tasks');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('people');
    }
};
