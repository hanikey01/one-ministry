<?php

namespace App\Http\Controllers;

use App\Models\ServiceChatMessage;
use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffChatModerationController extends Controller
{
    public function show(ServiceOccurrence $service): JsonResponse
    {
        $record = DB::table('service_chat_settings')->where('service_occurrence_id', $service->id)->first();
        $settings = $record ? ['paused' => (bool) $record->paused, 'slow_mode_seconds' => (int) $record->slow_mode_seconds, 'updated_by' => $record->updated_by, 'created_at' => $record->created_at, 'updated_at' => $record->updated_at] : ['paused' => false, 'slow_mode_seconds' => 0];

        return response()->json(['data' => [
            'settings' => $settings,
            'messages' => ServiceChatMessage::query()->where('service_occurrence_id', $service->id)->latest('created_at')->limit(100)->get(['id', 'display_name', 'is_host', 'content', 'moderated_at', 'moderation_reason', 'created_at']),
            'events' => DB::table('service_chat_moderation_events')->where('service_occurrence_id', $service->id)->latest('created_at')->limit(50)->get(),
        ]]);
    }

    public function settings(Request $request, ServiceOccurrence $service): JsonResponse
    {
        $data = $request->validate(['paused' => ['required', 'boolean'], 'slow_mode_seconds' => ['required', 'integer', 'min:0', 'max:300'], 'reason' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $service, $data): void {
            DB::table('service_chat_settings')->updateOrInsert(['service_occurrence_id' => $service->id], ['paused' => $data['paused'], 'slow_mode_seconds' => $data['slow_mode_seconds'], 'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('service_chat_moderation_events')->insert(['id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id, 'action' => 'settings_changed', 'reason' => $data['reason'] ?? null, 'details_json' => json_encode(['paused' => $data['paused'], 'slow_mode_seconds' => $data['slow_mode_seconds']]), 'actor_user_id' => $request->user()->id, 'created_at' => now()]);
        });

        return $this->show($service);
    }

    public function mute(Request $request, ServiceOccurrence $service, ServiceChatMessage $message): JsonResponse
    {
        abort_unless($message->service_occurrence_id === $service->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440']]);
        abort_if($message->user_id === null && $message->guest_key_hash === null, 422, 'This earlier guest message cannot be linked to a participant.');
        DB::transaction(function () use ($request, $service, $message, $data): void {
            DB::table('service_chat_blocks')->insert(['id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id, 'user_id' => $message->user_id, 'guest_key_hash' => $message->guest_key_hash, 'reason' => $data['reason'], 'expires_at' => isset($data['duration_minutes']) ? now()->addMinutes($data['duration_minutes']) : null, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('service_chat_moderation_events')->insert(['id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id, 'message_id' => $message->id, 'action' => 'participant_muted', 'reason' => $data['reason'], 'details_json' => json_encode(['duration_minutes' => $data['duration_minutes'] ?? null]), 'actor_user_id' => $request->user()->id, 'created_at' => now()]);
        });

        return response()->json(['data' => ['status' => 'muted']]);
    }
}
