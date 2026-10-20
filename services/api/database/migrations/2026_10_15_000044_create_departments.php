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
            $table->string('status', 20)->default('active');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'name']);
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

        DB::table('permissions')->insertOrIgnore([
            ['key' => 'departments.manage', 'description' => 'Create departments and appoint department leaders', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('role_permissions')->insertOrIgnore([
            ['role_key' => 'ministry_administrator', 'permission_key' => 'departments.manage'],
        ]);

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

