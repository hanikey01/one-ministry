<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\ServiceChatMessage;
use App\Models\ServiceOccurrence;
use App\Services\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceChatController extends Controller
{
    public function index(ServiceOccurrence $service): JsonResponse
    {
        abort_unless(FeatureSetting::query()->where('key', 'digital_church')->value('state') === 'enabled', 404);
        abort_unless(! $service->rehearsal_mode && $service->state === 'live', 404);

        $settings = DB::table('service_chat_settings')->where('service_occurrence_id', $service->id)->first();

        return response()->json(['data' => ServiceChatMessage::query()->where('service_occurrence_id', $service->id)->whereNull('moderated_at')->oldest('created_at')->limit(100)->get(['id', 'display_name', 'is_host', 'content', 'created_at']), 'meta' => ['paused' => (bool) ($settings?->paused ?? false), 'slow_mode_seconds' => (int) ($settings?->slow_mode_seconds ?? 0)]]);
    }

    public function store(Request $request, ServiceOccurrence $service, PermissionChecker $permissions): JsonResponse
    {
        abort_unless(FeatureSetting::query()->where('key', 'digital_church')->value('state') === 'enabled', 404);
        abort_unless(! $service->rehearsal_mode && $service->state === 'live', 422, 'Chat is available only during a live service.');
        $data = $request->validate(['display_name' => ['required', 'string', 'max:80'], 'content' => ['required', 'string', 'max:1000'], 'guest_key' => ['nullable', 'string', 'min:16', 'max:200']]);
        $isHost = $request->user() !== null && $permissions->allows($request->user(), 'services.manage');
        abort_if(! $request->user() && empty($data['guest_key']), 422, 'A guest key is required for chat.');
        $guestHash = $request->user() ? null : hash('sha512', $data['guest_key']);
        $settings = DB::table('service_chat_settings')->where('service_occurrence_id', $service->id)->first();
        abort_if(($settings?->paused ?? false) && ! $isHost, 409, 'Chat is temporarily paused by the host.');
        $blocked = DB::table('service_chat_blocks')->where('service_occurrence_id', $service->id)->where(fn ($query) => $request->user() ? $query->where('user_id', $request->user()->id) : $query->where('guest_key_hash', $guestHash))->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
        abort_if($blocked, 403, 'You cannot post in this service chat.');
        $slowMode = (int) ($settings?->slow_mode_seconds ?? 0);
        if (! $isHost && $slowMode > 0) {
            $latest = ServiceChatMessage::query()->where('service_occurrence_id', $service->id)->where($request->user() ? 'user_id' : 'guest_key_hash', $request->user()?->id ?? $guestHash)->latest('created_at')->value('created_at');
            abort_if($latest && now()->diffInSeconds($latest) < $slowMode, 429, "Slow mode is active. Wait {$slowMode} seconds between messages.");
        }
        $message = ServiceChatMessage::create(['service_occurrence_id' => $service->id, 'user_id' => $request->user()?->id, 'guest_key_hash' => $guestHash, 'display_name' => trim($data['display_name']), 'is_host' => $isHost, 'content' => trim($data['content']), 'created_at' => now()]);

        return response()->json(['data' => $message->only(['id', 'display_name', 'is_host', 'content', 'created_at'])], 201);
    }

    public function moderate(Request $request, ServiceOccurrence $service, ServiceChatMessage $message): JsonResponse
    {
        abort_unless($message->service_occurrence_id === $service->id, 404);
        abort_if($message->moderated_at !== null, 409, 'Message is already moderated.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $message->update(['moderated_at' => now(), 'moderated_by' => $request->user()->id, 'moderation_reason' => $data['reason']]);
        DB::transaction(function () use ($request, $service, $message, $data): void {
            DB::table('service_chat_moderation_events')->insert(['id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id, 'message_id' => $message->id, 'action' => 'message_removed', 'reason' => $data['reason'], 'details_json' => '{}', 'actor_user_id' => $request->user()->id, 'created_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => 'service_chat.message_removed', 'subject_type' => 'service_chat_messages', 'subject_id' => $message->id, 'changes_json' => json_encode(['reason' => $data['reason']]), 'ip_address' => $request->ip(), 'created_at' => now()]);
        });

        return response()->json(null, 204);
    }
}
