<?php

namespace App\Services;

use App\Models\ServiceOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceLifecycle
{
    private const TRANSITIONS = ['draft' => ['scheduled', 'cancelled'], 'scheduled' => ['ready', 'cancelled'], 'ready' => ['live', 'cancelled'], 'live' => ['interrupted', 'ended'], 'interrupted' => ['live', 'ended']];

    public function __construct(private LiveStreamService $streams) {}

    public function transition(ServiceOccurrence $service, string $state, User $actor, ?string $ip): ServiceOccurrence
    {
        if (! in_array($state, self::TRANSITIONS[$service->state] ?? [], true)) {
            throw ValidationException::withMessages(['state' => "Cannot change a {$service->state} service to {$state}."]);
        }
        if ($state === 'live' && ! $service->manual_stream_override && ! $this->streams->usable($service)) {
            throw ValidationException::withMessages(['state' => 'A healthy stream or explicit manual override is required before going live.']);
        }

        return DB::transaction(function () use ($service, $state, $actor, $ip): ServiceOccurrence {
            $service->update(['state' => $state]);
            $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id);
            if ($session->exists()) {
                $changes = ['status' => $state, 'updated_at' => now()];
                if ($state === 'live') {
                    $changes['started_at'] = DB::raw('COALESCE(started_at, CURRENT_TIMESTAMP)');
                    $changes['ended_at'] = null;
                } elseif (in_array($state, ['ended', 'cancelled'], true)) {
                    $changes['ended_at'] = now();
                }
                $session->update($changes);
            }
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => 'service.state_changed', 'subject_type' => 'service_occurrences', 'subject_id' => $service->id, 'changes_json' => json_encode(['state' => $state]), 'ip_address' => $ip, 'created_at' => now()]);

            return $service->fresh();
        });
    }
}
