<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_certificates', function (Blueprint $table): void {
            $table->dropUnique(['enrollment_id']);
            $table->foreignUuid('supersedes_certificate_id')->nullable()->after('enrollment_id')->constrained('school_certificates')->nullOnDelete();
            $table->foreignUuid('revoked_by_user_id')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->string('revoked_reason', 500)->nullable()->after('revoked_by_user_id');
            $table->index(['enrollment_id', 'revoked_at'], 'school_certificate_enrollment_status');
        });

        Schema::create('school_course_instructors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('school_courses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['course_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_course_instructors');
        Schema::table('school_certificates', function (Blueprint $table): void {
            $table->dropIndex('school_certificate_enrollment_status');
            $table->dropConstrainedForeignId('supersedes_certificate_id');
            $table->dropConstrainedForeignId('revoked_by_user_id');
            $table->dropColumn('revoked_reason');
            $table->unique('enrollment_id');
        });
    }
};
