<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaybackDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_playback_events_are_deduplicated_and_only_aggregated_for_staff(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'live']);
        $eventId = (string) Str::uuid();
        $base = ['event_id' => $eventId, 'session_key' => 'anonymous-session-one', 'event_type' => 'playback_started', 'playback_type' => 'hls', 'quality' => 'standard', 'startup_ms' => 1400];
        $this->postJson("/api/v1/public/services/{$service->id}/playback-diagnostics", $base)->assertCreated()->assertJsonPath('data.status', 'recorded');
        $this->postJson("/api/v1/public/services/{$service->id}/playback-diagnostics", $base)->assertOk()->assertJsonPath('data.status', 'duplicate');
        foreach ([
            ['session_key' => 'anonymous-session-one', 'event_type' => 'playback_stalled', 'rebuffer_ms' => 800],
            ['session_key' => 'anonymous-session-one', 'event_type' => 'playback_recovered', 'rebuffer_ms' => 800],
            ['session_key' => 'anonymous-session-two', 'event_type' => 'room_opened'],
            ['session_key' => 'anonymous-session-two', 'event_type' => 'heartbeat'],
        ] as $event) {
            $this->postJson("/api/v1/public/services/{$service->id}/playback-diagnostics", [...$event, 'event_id' => (string) Str::uuid(), 'playback_type' => 'hls', 'quality' => 'standard'])->assertCreated();
        }
        $this->assertDatabaseCount('playback_diagnostic_events', 5);
        $this->assertFalse(Schema::hasColumn('playback_diagnostic_events', 'user_id'));
        $this->assertFalse(Schema::hasColumn('playback_diagnostic_events', 'ip_address'));

        $this->getJson("/api/v1/staff/services/{$service->id}/playback-diagnostics")->assertUnauthorized();
        $this->actingAs($admin)->getJson("/api/v1/staff/services/{$service->id}/playback-diagnostics")
            ->assertOk()->assertJsonPath('data.active_recently', 2)->assertJsonPath('data.viewer_sessions', 2)
            ->assertJsonPath('data.starts', 1)->assertJsonPath('data.stalls', 1)
            ->assertJsonPath('data.recoveries', 1)->assertJsonPath('data.failures', 0)
            ->assertJsonPath('data.average_startup_ms', 1400);
    }

    public function test_diagnostics_are_unavailable_outside_public_live_service(): void
    {
        app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'scheduled']);
        $this->postJson("/api/v1/public/services/{$service->id}/playback-diagnostics", ['event_id' => (string) Str::uuid(), 'session_key' => 'anonymous-session-one', 'event_type' => 'room_opened'])->assertNotFound();
    }
}
