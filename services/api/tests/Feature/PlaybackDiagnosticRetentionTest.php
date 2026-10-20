<?php

namespace Tests\Feature;

use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaybackDiagnosticRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_expired_events_and_keeps_recent_events(): void
    {
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'live']);
        foreach ([now()->subDays(8), now()->subDays(2)] as $createdAt) {
            DB::table('playback_diagnostic_events')->insert(['id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id, 'client_event_id' => (string) Str::uuid(), 'session_key_hash' => hash('sha256', (string) Str::uuid()), 'event_type' => 'heartbeat', 'created_at' => $createdAt]);
        }
        $this->artisan('ministry:prune-playback-diagnostics', ['--days' => 7])->assertSuccessful();
        $this->assertDatabaseCount('playback_diagnostic_events', 1);
    }
}
