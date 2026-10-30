<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_courses', function (Blueprint $table): void {
            $table->string('certificate_template_key', 40)->default('classic');
        });
    }

    public function down(): void
    {
        Schema::table('school_courses', function (Blueprint $table): void {
            $table->dropColumn('certificate_template_key');
        });
    }
};
