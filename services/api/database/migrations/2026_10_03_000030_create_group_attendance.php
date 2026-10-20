<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_gatherings', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $t->timestamp('gathered_at');
            $t->string('title', 160)->nullable();
            $t->timestamps();
            $t->index(['group_id', 'gathered_at']);
        });
        Schema::create('group_attendance_records', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('group_gathering_id')->constrained('group_gatherings')->cascadeOnDelete();
            $t->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $t->string('status', 20)->default('present');
            $t->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['group_gathering_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_attendance_records');
        Schema::dropIfExists('group_gatherings');
    }
};
