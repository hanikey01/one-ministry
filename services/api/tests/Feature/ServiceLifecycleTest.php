<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\ServiceOccurrence;
use App\Services\ServiceLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_requires_override_and_transitions_are_audited(): void
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $service = ServiceOccurrence::create(['title' => 'Sunday Worship', 'timezone' => 'Africa/Lagos', 'starts_at' => now(), 'state' => 'draft']);
        $lifecycle = app(ServiceLifecycle::class);
        $lifecycle->transition($service, 'scheduled', $admin, null);
        $service = $lifecycle->transition($service->fresh(), 'ready', $admin, null);
        try {
            $lifecycle->transition($service, 'live', $admin, null);
            $this->fail('Expected stream override validation.');
        } catch (ValidationException) {
        }
        $service->update(['manual_stream_override' => true]);
        $lifecycle->transition($service->fresh(), 'live', $admin, null);
        $this->assertDatabaseHas('audit_events', ['action' => 'service.state_changed', 'subject_id' => $service->id]);
    }
}
