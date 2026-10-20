<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_availability', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('state', 20)->default('available');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['person_id', 'starts_at', 'ends_at']);
        });
        Schema::create('volunteer_substitution_requests', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('assignment_id')->constrained('volunteer_assignments')->cascadeOnDelete();
            $t->foreignUuid('requested_by')->constrained('people')->restrictOnDelete();
            $t->foreignUuid('replacement_person_id')->nullable()->constrained('people')->nullOnDelete();
            $t->string('status', 20)->default('open');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::table('volunteer_assignments', function (Blueprint $t): void {
            $t->timestamp('reminder_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->index(['status', 'reminder_at']);
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_assignments', function (Blueprint $t): void {
            $t->dropIndex(['status', 'reminder_at']);
            $t->dropColumn(['reminder_at', 'reminded_at']);
        });
        Schema::dropIfExists('volunteer_substitution_requests');
        Schema::dropIfExists('volunteer_availability');
    }
};
