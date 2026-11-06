<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160)->unique();
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->string('image_alt', 255)->nullable();
            $table->string('category', 80)->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->boolean('featured')->default(false);
            $table->string('status', 20)->default('active');
            $table->boolean('is_public')->default(false);
            $table->boolean('accepting_requests')->default(false);
            $table->boolean('discussion_enabled')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
            $table->index(['status', 'name']);
            $table->index(['status', 'is_public', 'name'], 'departments_public_idx');
        });

        Schema::create('department_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->restrictOnDelete();
            $table->string('role', 24)->default('member');
            $table->string('status', 20)->default('active');
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'person_id']);
            $table->index(['department_id', 'role', 'status']);
            $table->index(['person_id', 'status']);
        });

        Schema::create('department_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status', 'created_at'], 'department_requests_status_idx');
            $table->index(['person_id', 'status'], 'department_requests_person_idx');
        });

        Schema::create('department_gatherings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_id')->constrained('departments')->cascadeOnDelete();
            $table->timestamp('gathered_at');
            $table->string('title', 160)->nullable();
            $table->timestamps();
            $table->index(['department_id', 'gathered_at'], 'department_gatherings_date_idx');
        });

        Schema::create('department_attendance_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_gathering_id')->constrained('department_gatherings')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('status', 20)->default('present');
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['department_gathering_id', 'person_id'], 'department_attendance_unique');
        });

        Schema::create('department_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->uuid('parent_message_id')->nullable();
            $table->text('content');
            $table->timestamp('edited_at')->nullable();
            $table->foreignUuid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_reason', 240)->nullable();
            $table->timestamps();
            $table->index(['department_id', 'created_at'], 'department_messages_date_idx');
        });
        Schema::table('department_messages', function (Blueprint $table): void {
            $table->foreign('parent_message_id')->references('id')->on('department_messages')->nullOnDelete();
        });
        Schema::create('department_message_reads', function (Blueprint $table): void {
            $table->foreignUuid('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->primary(['department_id','person_id']);
        });

        DB::table('permissions')->insertOrIgnore([
            ['key' => 'departments.manage', 'description' => 'Create departments and appoint department leaders', 'created_at' => now(), 'updated_at' => now()],
        ]);
        if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
            DB::table('role_permissions')->insertOrIgnore([
                ['role_key' => 'ministry_administrator', 'permission_key' => 'departments.manage'],
            ]);
        }

        if (DB::table('ministry_settings')->exists()) {
            foreach (self::defaults() as $department) {
                DB::table('departments')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    ...$department,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'departments.manage')->delete();
        DB::table('permissions')->where('key', 'departments.manage')->delete();
        Schema::dropIfExists('department_message_reads');
        Schema::dropIfExists('department_messages');
        Schema::dropIfExists('department_attendance_records');
        Schema::dropIfExists('department_gatherings');
        Schema::dropIfExists('department_join_requests');
        Schema::dropIfExists('department_memberships');
        Schema::dropIfExists('departments');
    }

    private static function defaults(): array
    {
        return [
            ['name' => 'Media & Production', 'slug' => 'media-production', 'description' => 'Visual, audio, livestream, projection, and service production.'],
            ['name' => 'Prayer', 'slug' => 'prayer', 'description' => 'Prayer gatherings and prayer-team coordination.'],
            ['name' => 'Worship', 'slug' => 'worship', 'description' => 'Music, worship leaders, and service musicians.'],
            ['name' => 'Hospitality & Ushering', 'slug' => 'hospitality-ushering', 'description' => 'Welcome, seating, and guest hospitality.'],
            ['name' => 'Follow-up & Connections', 'slug' => 'follow-up-connections', 'description' => 'Visitor welcome and connection follow-up.'],
            ['name' => 'Outreach & Missions', 'slug' => 'outreach-missions', 'description' => 'Community outreach and mission activities.'],
            ['name' => 'Technical & Livestream', 'slug' => 'technical-livestream', 'description' => 'Technical systems, streaming, and digital service support.'],
            ['name' => 'Protocol', 'slug' => 'protocol', 'description' => 'Guest, speaker, and service protocol.'],
            ['name' => 'Communications', 'slug' => 'communications', 'description' => 'Notices, social channels, photography, and communication support.'],
            ['name' => 'Care', 'slug' => 'care', 'description' => 'Care-team coordination. Restricted care records need separate access.'],
            ['name' => 'Finance', 'slug' => 'finance', 'description' => 'Finance-team coordination. Giving records need separate access.'],
        ];
    }
};

