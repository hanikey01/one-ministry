<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 160);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });
        Schema::create('group_memberships', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $t->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $t->string('role', 20)->default('member');
            $t->timestamps();
            $t->unique(['group_id', 'person_id']);
        });
        Schema::create('events', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->string('location', 200)->nullable();
            $t->unsignedInteger('capacity')->nullable();
            $t->boolean('registration_open')->default(true);
            $t->timestamps();
        });
        Schema::create('event_registrations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $t->string('name', 160);
            $t->string('email', 254)->nullable();
            $t->string('status', 20)->default('registered');
            $t->timestamps();
            $t->unique(['event_id', 'email']);
        });
        Schema::create('announcements', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('title', 200);
            $t->text('body');
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('published')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('group_memberships');
        Schema::dropIfExists('groups');
    }
};
