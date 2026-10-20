<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('provider', 40)->default('zoom_video_sdk')->after('title');
            $table->string('session_name', 200)->nullable()->unique()->after('provider');
            $table->text('session_passcode_encrypted')->nullable()->after('session_name');
            $table->string('access_policy', 32)->default('member_only')->after('status');
            $table->string('entry_policy', 32)->default('waiting_room')->after('access_policy');
            $table->string('participant_media_policy', 32)->default('camera_mic_allowed')->after('entry_policy');
            $table->string('screen_share_policy', 32)->default('presenter_host_only')->after('participant_media_policy');
            $table->string('chat_policy', 32)->default('enabled')->after('screen_share_policy');
            $table->string('recording_policy', 32)->default('off')->after('recording_notice');
            $table->boolean('breakouts_enabled')->default(false)->after('recording_policy');
            $table->unsignedInteger('declared_capacity')->nullable()->after('breakouts_enabled');
            $table->json('provider_settings_json')->nullable()->after('declared_capacity');
        });

        Schema::table('meeting_participants', function (Blueprint $table): void {
            $table->string('admission_status', 24)->default('invited')->after('role');
            $table->string('provider_identity', 36)->nullable()->after('admission_status');
            $table->timestamp('admitted_at')->nullable()->after('expires_at');
            $table->timestamp('joined_at')->nullable()->after('admitted_at');
            $table->timestamp('left_at')->nullable()->after('joined_at');
            $table->index(['meeting_id', 'admission_status']);
            $table->index(['meeting_id', 'user_id']);
        });

        DB::table('meetings')->orderBy('created_at')->each(function (object $meeting): void {
            DB::table('meetings')->where('id', $meeting->id)->update([
                'provider' => 'zoom_video_sdk',
                'session_name' => 'ministry-'.substr(str_replace('-', '', $meeting->id), 0, 28),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('meeting_participants', function (Blueprint $table): void {
            $table->dropIndex(['meeting_id', 'admission_status']);
            $table->dropIndex(['meeting_id', 'user_id']);
            $table->dropColumn(['admission_status', 'provider_identity', 'admitted_at', 'joined_at', 'left_at']);
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropUnique(['session_name']);
            $table->dropColumn(['provider', 'session_name', 'session_passcode_encrypted', 'access_policy', 'entry_policy', 'participant_media_policy', 'screen_share_policy', 'chat_policy', 'recording_policy', 'breakouts_enabled', 'declared_capacity', 'provider_settings_json']);
        });
    }
};
