<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->string('slug', 191)->unique();
            $table->string('category', 100)->nullable();
            $table->string('level', 30)->default('all_levels');
            $table->string('instructor_name', 160)->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('enrollment_policy', 24)->default('open');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('certificate_enabled')->default(true);
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('school_course_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('school_courses')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['course_id', 'position']);
        });

        Schema::create('school_lessons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('school_course_modules')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('type', 24)->default('text');
            $table->longText('content')->nullable();
            $table->string('media_url', 1000)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('preview')->default(false);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['module_id', 'position']);
            $table->index(['module_id', 'published']);
        });

        Schema::create('school_enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('school_courses')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('status', 24)->default('active');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('enrolled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'person_id']);
            $table->index(['person_id', 'status']);
        });

        Schema::create('school_lesson_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('school_enrollments')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('school_lessons')->cascadeOnDelete();
            $table->string('status', 20)->default('started');
            $table->unsignedInteger('progress_seconds')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'lesson_id']);
        });

        Schema::create('school_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('school_courses')->cascadeOnDelete();
            $table->foreignUuid('module_id')->nullable()->constrained('school_course_modules')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('school_assessment_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('school_assessments')->cascadeOnDelete();
            $table->text('prompt');
            $table->string('type', 24)->default('single_choice');
            $table->json('options_json')->nullable();
            $table->json('correct_answer_json');
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['assessment_id', 'position']);
        });

        Schema::create('school_assessment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('school_assessments')->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('school_enrollments')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->json('answers_json');
            $table->unsignedSmallInteger('earned_points')->default(0);
            $table->unsignedSmallInteger('possible_points')->default(0);
            $table->unsignedTinyInteger('score_percent')->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['assessment_id', 'enrollment_id', 'attempt_number'], 'school_attempt_unique');
        });

        Schema::create('school_live_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('school_courses')->cascadeOnDelete();
            $table->foreignUuid('module_id')->nullable()->constrained('school_course_modules')->nullOnDelete();
            $table->foreignUuid('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 80)->default('UTC');
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->index(['course_id', 'starts_at']);
        });

        Schema::create('school_certificates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->unique()->constrained('school_enrollments')->cascadeOnDelete();
            $table->string('certificate_number', 40)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });

        DB::table('feature_settings')->insertOrIgnore([
            'key' => 'digital_school', 'state' => 'disabled', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('roles')->insertOrIgnore([
            'key' => 'school_manager', 'name' => 'School Manager', 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['school.manage' => 'Create and operate courses, classes, enrolments, and assessments', 'school.instruct' => 'Teach courses and review learner progress'] as $key => $description) {
            DB::table('permissions')->insertOrIgnore(['key' => $key, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'school_manager', 'permission_key' => $key]);
            foreach (['ministry_administrator', 'super_administrator'] as $role) {
                if (DB::table('roles')->where('key', $role)->exists()) {
                    DB::table('role_permissions')->insertOrIgnore(['role_key' => $role, 'permission_key' => $key]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_certificates');
        Schema::dropIfExists('school_live_classes');
        Schema::dropIfExists('school_assessment_attempts');
        Schema::dropIfExists('school_assessment_questions');
        Schema::dropIfExists('school_assessments');
        Schema::dropIfExists('school_lesson_progress');
        Schema::dropIfExists('school_enrollments');
        Schema::dropIfExists('school_lessons');
        Schema::dropIfExists('school_course_modules');
        Schema::dropIfExists('school_courses');
        DB::table('role_permissions')->whereIn('permission_key', ['school.manage', 'school.instruct'])->delete();
        DB::table('permissions')->whereIn('key', ['school.manage', 'school.instruct'])->delete();
        DB::table('roles')->where('key', 'school_manager')->delete();
        DB::table('feature_settings')->where('key', 'digital_school')->delete();
    }
};
