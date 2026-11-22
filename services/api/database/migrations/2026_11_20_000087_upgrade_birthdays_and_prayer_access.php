<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'prayer.read' => 'Read prayer requests routed to administrators and the prayer team',
            'birthdays.read' => 'Receive and view ministry birthday reminders',
        ] as $key => $description) {
            DB::table('permissions')->insertOrIgnore([
                'key' => $key,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('roles')->insertOrIgnore([
            'key' => 'prayer_team',
            'name' => 'Prayer Team',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insertOrIgnore([
            ['role_key' => 'prayer_team', 'permission_key' => 'prayer.read'],
            ['role_key' => 'ministry_administrator', 'permission_key' => 'prayer.read'],
            ['role_key' => 'ministry_administrator', 'permission_key' => 'birthdays.read'],
            ['role_key' => 'ministry_operations_coordinator', 'permission_key' => 'birthdays.read'],
        ]);

        DB::table('prayer_requests')->where('visibility', 'assigned_care_only')->update([
            'visibility' => 'prayer_team',
            'assigned_to' => null,
            'assigned_at' => null,
            'updated_at' => $now,
        ]);
        if (Schema::hasTable('service_prayer_requests')) {
            DB::table('service_prayer_requests')->where('visibility', 'assigned_care_only')->update(['visibility' => 'prayer_team']);
        }

        Schema::create('birthday_reminder_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignUuid('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('birthday_year');
            $table->string('reminder_type', 40)->default('staff_advance');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('failure_code', 120)->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['person_id', 'recipient_user_id', 'birthday_year', 'reminder_type'], 'birthday_reminder_delivery_unique');
            $table->index(['status', 'last_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_reminder_deliveries');
        DB::table('role_permissions')->whereIn('permission_key', ['prayer.read', 'birthdays.read'])->delete();
        DB::table('permissions')->whereIn('key', ['prayer.read', 'birthdays.read'])->delete();
        if (! DB::table('user_role_assignments')->where('role_key', 'prayer_team')->exists()
            && ! DB::table('staff_invitations')->where('role_key', 'prayer_team')->exists()) {
            DB::table('roles')->where('key', 'prayer_team')->delete();
        }
    }
};
