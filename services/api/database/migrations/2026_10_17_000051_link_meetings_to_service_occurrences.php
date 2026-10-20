<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->foreignUuid('service_occurrence_id')->nullable()->unique()->after('created_by')->constrained('service_occurrences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropForeign(['service_occurrence_id']);
            $table->dropUnique(['service_occurrence_id']);
            $table->dropColumn('service_occurrence_id');
        });
    }
};
