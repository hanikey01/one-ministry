<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_certificates', function (Blueprint $table): void {
            $table->string('verification_code', 32)->nullable()->unique();
            $table->string('recipient_name', 200)->nullable();
            $table->string('course_title', 200)->nullable();
            $table->string('issuer_name', 200)->nullable();
            $table->string('template_key', 40)->default('classic');
        });
    }

    public function down(): void
    {
        Schema::table('school_certificates', function (Blueprint $table): void {
            $table->dropUnique(['verification_code']);
            $table->dropColumn(['verification_code', 'recipient_name', 'course_title', 'issuer_name', 'template_key']);
        });
    }
};
