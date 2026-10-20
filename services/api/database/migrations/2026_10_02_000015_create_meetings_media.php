<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('created_by')->constrained('users');
            $t->string('title', 200);
            $t->string('status', 20)->default('scheduled');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('recording_notice')->default(true);
            $t->timestamps();
        });
        Schema::create('meeting_participants', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('display_name', 160);
            $t->string('role', 20)->default('participant');
            $t->string('access_token_hash', 128)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('media_assets', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('provider', 40);
            $t->string('provider_key', 255);
            $t->string('status', 20)->default('draft');
            $t->string('kind', 30)->default('sermon');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'kind']);
        });
        Schema::create('meeting_recordings', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->string('provider_key', 255);
            $t->string('status', 20)->default('pending_review');
            $t->text('review_notes')->nullable();
            $t->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_recordings');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
