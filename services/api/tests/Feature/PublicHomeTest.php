<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lead_uses_actual_service_state_and_not_schedule_alone(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $service = ServiceOccurrence::create(['title' => 'Sunday Worship', 'timezone' => 'Africa/Lagos', 'starts_at' => now()->subMinute(), 'state' => 'scheduled']);
        $this->getJson('/api/v1/public/home')->assertOk()->assertJsonPath('data.lead_state', 'carousel');
        $service->update(['state' => 'live', 'manual_stream_override' => true]);
        $this->getJson('/api/v1/public/home')->assertOk()->assertJsonPath('data.lead_state', 'live')->assertJsonPath('data.service.title', 'Sunday Worship');
        $service->update(['state' => 'interrupted']);
        $this->getJson('/api/v1/public/home')->assertOk()->assertJsonPath('data.lead_state', 'interrupted');
    }

    public function test_public_worship_room_requires_enabled_digital_church_and_live_service(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $service = ServiceOccurrence::create(['title' => 'Sunday Worship', 'timezone' => 'Africa/Lagos', 'starts_at' => now(), 'state' => 'live', 'manual_stream_override' => true]);

        $this->getJson("/api/v1/public/services/{$service->id}")->assertNotFound();
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $this->getJson("/api/v1/public/services/{$service->id}")->assertOk()->assertJsonPath('data.title', 'Sunday Worship')->assertJsonPath('data.media.state', 'unavailable')->assertJsonPath('data.chat_enabled', true);
    }
}
