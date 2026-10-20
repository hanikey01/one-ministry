<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('title', 160);
            $table->string('timezone', 64);
            $table->unsignedInteger('default_duration_minutes')->default(90);
            $table->json('run_sheet_defaults')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('service_occurrences', function (Blueprint $table): void {
            $table->foreignUuid('service_template_id')->nullable()->after('id')->constrained('service_templates')->nullOnDelete();
            $table->boolean('rehearsal_mode')->default(false)->after('manual_stream_override');
        });
    }

    public function down(): void
    {
        Schema::table('service_occurrences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_template_id');
            $table->dropColumn('rehearsal_mode');
        });
        Schema::dropIfExists('service_templates');
    }
};
