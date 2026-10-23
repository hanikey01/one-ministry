<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->index('starts_at', 'events_starts_at_index');
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->index(['person_id', 'status', 'event_id'], 'event_registrations_member_listing_index');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropIndex('event_registrations_member_listing_index');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_starts_at_index');
        });
    }
};
