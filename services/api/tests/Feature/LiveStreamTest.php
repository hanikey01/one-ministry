<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_configured_stream_can_go_live_and_offline_health_interrupts_it(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $service = ServiceOccurrence::create([
            'title' => 'Sunday Worship', 'timezone' => 'Africa/Lagos', 'starts_at' => now(),
            'state' => 'ready', 'manual_stream_override' => false,
        ]);
        $this->actingAs($admin);

        $this->putJson("/api/v1/staff/services/{$service->id}/stream", [
            'provider' => 'youtube', 'playback_url' => 'https://www.youtube.com/live/AbCdEf12345',
            'fallback_message' => 'Our video team is restoring the broadcast.',
        ])->assertOk()->assertJsonPath('data.provider', 'youtube')->assertJsonPath('data.provider_stream_id', 'AbCdEf12345');
        $this->patchJson("/api/v1/staff/services/{$service->id}/stream/health", [
            'ingest_health' => 'healthy', 'playback_health' => 'healthy', 'reason' => 'Encoder and viewer probe checked.',
        ])->assertOk();
        $this->postJson("/api/v1/staff/services/{$service->id}/transition", ['state' => 'live'])
            ->assertOk()->assertJsonPath('data.state', 'live');

        $public = $this->getJson("/api/v1/public/services/{$service->id}")
            ->assertOk()->assertJsonPath('data.media.state', 'available')
            ->assertJsonPath('data.media.playback_type', 'youtube')
            ->assertJsonPath('data.media.playback_url', 'https://www.youtube-nocookie.com/embed/AbCdEf12345?autoplay=1&rel=0');
        $this->assertArrayNotHasKey('provider_stream_id', $public->json('data.media'));

        $this->patchJson("/api/v1/staff/services/{$service->id}/stream/health", [
            'ingest_health' => 'offline', 'playback_health' => 'offline', 'reason' => 'Encoder disconnected.',
        ])->assertOk();
        $this->assertDatabaseHas('service_occurrences', ['id' => $service->id, 'state' => 'interrupted']);
        $this->getJson("/api/v1/public/services/{$service->id}")
            ->assertOk()->assertJsonPath('data.state', 'interrupted')
            ->assertJsonPath('data.media.state', 'unavailable')
            ->assertJsonPath('data.media.message', 'Our video team is restoring the broadcast.');
        $this->assertDatabaseCount('live_stream_events', 4);
        $this->assertDatabaseHas('audit_events', ['action' => 'service.state_changed', 'subject_id' => $service->id]);
    }

    public function test_stream_configuration_rejects_unsafe_or_invalid_playback_urls(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'draft']);
        $this->actingAs($admin)->putJson("/api/v1/staff/services/{$service->id}/stream", [
            'provider' => 'hls', 'playback_url' => 'http://media.example.org/live.m3u8',
        ])->assertUnprocessable();
        $this->putJson("/api/v1/staff/services/{$service->id}/stream", [
            'provider' => 'hls', 'playback_url' => 'https://media.example.org/watch',
        ])->assertUnprocessable();
        $this->putJson("/api/v1/staff/services/{$service->id}/stream", [
            'provider' => 'youtube', 'playback_url' => 'https://example.org/not-youtube',
        ])->assertUnprocessable();
    }

    public function test_signed_provider_health_is_deduplicated_and_interrupts_an_offline_service(): void
    {
        config(['services.livestream.webhook_secret' => 'test-stream-secret']);
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'live', 'manual_stream_override' => true]);
        $this->actingAs($admin)->putJson("/api/v1/staff/services/{$service->id}/stream", [
            'provider' => 'youtube', 'playback_url' => 'https://youtube.com/watch?v=AbCdEf12345',
        ])->assertOk();
        $payload = json_encode(['event_id' => 'provider-event-1', 'service_occurrence_id' => $service->id, 'ingest_health' => 'offline', 'playback_health' => 'offline', 'reason' => 'Provider reports source lost.']);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test-stream-secret');

        $this->call('POST', '/api/v1/webhooks/livestream/youtube', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_MINISTRY_SIGNATURE' => $signature], $payload)
            ->assertOk()->assertJsonPath('data.status', 'processed');
        $this->assertDatabaseHas('service_occurrences', ['id' => $service->id, 'state' => 'interrupted']);
        $this->call('POST', '/api/v1/webhooks/livestream/youtube', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_MINISTRY_SIGNATURE' => $signature], $payload)
            ->assertOk()->assertJsonPath('data.status', 'duplicate');
        $this->assertDatabaseCount('live_stream_events', 3);
        $this->withHeader('X-Ministry-Signature', 'sha256=invalid')->postJson('/api/v1/webhooks/livestream/youtube', json_decode($payload, true))->assertUnauthorized();
    }

    public function test_staff_can_run_a_safe_hls_playback_probe(): void
    {
        Http::fake(['https://1.1.1.1/*' => Http::response("#EXTM3U\n#EXT-X-VERSION:3", 200)]);
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'ready']);
        $this->actingAs($admin)->putJson("/api/v1/staff/services/{$service->id}/stream", [
            'provider' => 'hls', 'playback_url' => 'https://1.1.1.1/live.m3u8',
        ])->assertOk();
        $this->postJson("/api/v1/staff/services/{$service->id}/stream/probe")
            ->assertOk()->assertJsonPath('data.status', 'healthy')->assertJsonPath('data.http_status', 200);
        $this->assertDatabaseHas('live_sessions', ['service_occurrence_id' => $service->id, 'playback_health' => 'healthy', 'last_probe_http_status' => 200]);
        Http::assertSentCount(1);
    }
}
