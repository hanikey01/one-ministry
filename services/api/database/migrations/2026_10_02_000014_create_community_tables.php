<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('title', 200);
            $t->string('slug', 220)->unique();
            $t->text('description')->nullable();
            $t->string('image_url', 1000)->nullable();
            $t->string('image_alt', 255)->nullable();
            $t->string('category', 80)->nullable();
            $t->string('status', 20)->default('draft');
            $t->boolean('featured')->default(false);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->string('location', 200)->nullable();
            $t->string('online_url', 1000)->nullable();
            $t->unsignedInteger('capacity')->nullable();
            $t->timestamp('registration_deadline')->nullable();
            $t->boolean('registration_open')->default(true);
            $t->boolean('waitlist_enabled')->default(true);
            $t->timestamp('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('event_registrations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $t->string('name', 160);
            $t->string('email', 191)->nullable();
            $t->string('status', 20)->default('registered');
            $t->timestamps();
            $t->unique(['event_id', 'email']);
            $t->unique(['event_id', 'person_id']);
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
    }
};
