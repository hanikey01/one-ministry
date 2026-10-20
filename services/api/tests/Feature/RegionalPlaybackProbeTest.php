<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegionalPlaybackProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_probe_records_each_region_and_calculates_degraded_health(): void
    {
        config(['ministry.livestream_probe_regions' => [['key' => 'west-africa', 'label' => 'West Africa', 'endpoint' => 'https://1.1.1.1/probe', 'token' => 'region-secret']]]);
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://1.1.1.1/probe') {
                return Http::response(['status' => 'offline', 'http_status' => 503, 'latency_ms' => 850], 200);
            }

            return Http::response("#EXTM3U\n#EXT-X-VERSION:3", 200);
        });
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'ready']);
        $this->actingAs($admin)->putJson("/api/v1/staff/services/{$service->id}/stream", ['provider' => 'hls', 'playback_url' => 'https://1.1.1.1/live.m3u8'])->assertOk();

        $this->postJson("/api/v1/staff/services/{$service->id}/stream/regional-probes")
            ->assertCreated()->assertJsonPath('data.status', 'degraded')
            ->assertJsonCount(2, 'data.results');
        $this->assertDatabaseHas('live_stream_probe_results', ['region_key' => 'origin', 'status' => 'healthy']);
        $this->assertDatabaseHas('live_stream_probe_results', ['region_key' => 'west_africa', 'status' => 'offline', 'http_status' => 503]);
        $this->assertDatabaseHas('live_sessions', ['service_occurrence_id' => $service->id, 'playback_health' => 'degraded']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://1.1.1.1/probe' && $request->hasHeader('Authorization', 'Bearer region-secret'));

        $this->getJson("/api/v1/staff/services/{$service->id}/stream/regional-probes")
            ->assertOk()->assertJsonPath('data.regions.1.label', 'West Africa')
            ->assertJsonPath('data.provider_capabilities.manifest_probe', true)
            ->assertJsonPath('data.provider_capabilities.ingest_credentials_managed_here', false);
    }

    public function test_scheduler_probes_ready_sessions_and_endpoints_require_staff(): void
    {
        config(['ministry.livestream_probe_regions' => []]);
        Http::fake(['https://1.1.1.1/*' => Http::response("#EXTM3U\n", 200)]);
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'ready']);
        $this->actingAs($admin)->putJson("/api/v1/staff/services/{$service->id}/stream", ['provider' => 'hls', 'playback_url' => 'https://1.1.1.1/live.m3u8'])->assertOk();
        auth()->logout();
        $this->getJson("/api/v1/staff/services/{$service->id}/stream/regional-probes")->assertUnauthorized();
        $this->artisan('ministry:probe-live-streams', ['--json' => true])->assertSuccessful();
        $this->assertDatabaseHas('live_stream_probe_runs', ['trigger' => 'scheduled', 'status' => 'healthy']);
    }
}
