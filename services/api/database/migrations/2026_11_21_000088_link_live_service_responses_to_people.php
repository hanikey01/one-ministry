<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_connection_responses', function (Blueprint $table): void {
            $table->foreignUuid('person_id')->nullable()->after('service_occurrence_id')->constrained('people')->nullOnDelete();
        });
        Schema::table('service_prayer_requests', function (Blueprint $table): void {
            $table->foreignUuid('person_id')->nullable()->after('service_occurrence_id')->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_prayer_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('person_id');
        });
        Schema::table('service_connection_responses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
