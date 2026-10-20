<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_journeys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('follow_up_task_id')->nullable()->constrained('follow_up_tasks')->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 24)->default('requested');
            $table->boolean('consent_to_contact')->default(true);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['person_id', 'status']);
            $table->index(['person_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_journeys');
    }
};
