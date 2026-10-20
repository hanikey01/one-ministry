<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('channel', 16);
            $table->string('subject', 240)->nullable();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('message_templates')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('purpose', 24);
            $table->string('status', 24)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('message_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->restrictOnDelete();
            $table->string('channel', 16);
            $table->string('status', 24)->default('queued');
            $table->string('provider_message_id', 255)->nullable()->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'person_id']);
        });
        DB::table('permissions')->insertOrIgnore([
            'key' => 'communications.manage', 'description' => 'Manage ministry messages and campaigns',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (DB::table('roles')->where('key', 'ministry_administrator')->exists()) {
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => 'communications.manage']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'communications.manage')->delete();
        DB::table('permissions')->where('key', 'communications.manage')->delete();
        Schema::dropIfExists('message_deliveries');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('message_templates');
    }
};
