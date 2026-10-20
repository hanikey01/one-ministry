<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_chat_messages', function (Blueprint $table): void {
            $table->string('guest_key_hash', 128)->nullable()->after('user_id')->index();
            $table->boolean('is_host')->default(false)->after('display_name');
            $table->text('moderation_reason')->nullable()->after('moderated_by');
        });
        Schema::create('service_chat_settings', function (Blueprint $table): void {
            $table->foreignUuid('service_occurrence_id')->primary()->constrained('service_occurrences')->cascadeOnDelete();
            $table->boolean('paused')->default(false);
            $table->unsignedSmallInteger('slow_mode_seconds')->default(0);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        Schema::create('service_chat_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('guest_key_hash', 128)->nullable();
            $table->text('reason');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['service_occurrence_id', 'user_id']);
            $table->index(['service_occurrence_id', 'guest_key_hash']);
        });
        Schema::create('service_chat_moderation_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('service_chat_messages')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->text('reason')->nullable();
            $table->json('details_json');
            $table->foreignUuid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_chat_moderation_events');
        Schema::dropIfExists('service_chat_blocks');
        Schema::dropIfExists('service_chat_settings');
        Schema::table('service_chat_messages', function (Blueprint $table): void {
            $table->dropColumn(['guest_key_hash', 'is_host', 'moderation_reason']);
        });
    }
};
