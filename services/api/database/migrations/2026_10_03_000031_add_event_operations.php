<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t): void {
            $t->timestamp('reminder_at')->nullable();
        });
        Schema::table('event_registrations', function (Blueprint $t): void {
            $t->timestamp('checked_in_at')->nullable();
            $t->foreignUuid('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $t): void {
            $t->dropIndex(['event_id', 'status']);
            $t->dropConstrainedForeignId('checked_in_by');
            $t->dropColumn(['checked_in_at', 'cancelled_at']);
        });
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('reminder_at'));
    }
};
