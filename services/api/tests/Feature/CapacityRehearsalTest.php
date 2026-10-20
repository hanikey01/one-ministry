<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapacityRehearsalTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_calculates_capacity_result_and_rejects_an_under_target_claim(): void
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'scheduled']);
        $created = $this->actingAs($admin)->postJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals", ['expected_peak_viewers' => 1000, 'headroom_percent' => 25, 'geography' => 'West Africa', 'provider_plan' => 'Provider load test', 'target_startup_ms' => 3000, 'target_rebuffer_rate_bps' => 100, 'target_error_rate_bps' => 50, 'target_chat_latency_ms' => 2000])->assertCreated()->assertJsonPath('data.required_test_viewers', 1250);
        $id = $created->json('data.id');
        $this->actingAs($admin)->postJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals/{$id}/start")->assertOk()->assertJsonPath('data.status', 'running');
        $this->actingAs($admin)->postJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals/{$id}/complete", ['actual_peak_viewers' => 1249, 'measured_p95_startup_ms' => 2400, 'measured_rebuffer_rate_bps' => 80, 'measured_error_rate_bps' => 30, 'measured_chat_p95_ms' => 1500])->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.criteria.viewer_load.passed', false);
        $this->assertDatabaseHas('audit_events', ['action' => 'capacity_rehearsal.completed', 'subject_id' => $id]);
    }

    public function test_all_targets_must_pass_and_routes_require_authorization(): void
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'scheduled']);
        $this->getJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals")->assertUnauthorized();
        $id = $this->actingAs($admin)->postJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals", ['expected_peak_viewers' => 100, 'headroom_percent' => 20, 'geography' => 'Global', 'target_startup_ms' => 3000, 'target_rebuffer_rate_bps' => 100, 'target_error_rate_bps' => 100, 'target_chat_latency_ms' => 2000])->json('data.id');
        $this->actingAs($admin)->postJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals/{$id}/start")->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/staff/services/{$service->id}/capacity-rehearsals/{$id}/complete", ['actual_peak_viewers' => 120, 'measured_p95_startup_ms' => 3000, 'measured_rebuffer_rate_bps' => 100, 'measured_error_rate_bps' => 100, 'measured_chat_p95_ms' => 2000])->assertOk()->assertJsonPath('data.status', 'passed');
    }
}
