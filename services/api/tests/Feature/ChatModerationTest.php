<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosts_can_pause_slow_remove_and_mute_service_chat(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'live']);
        $guest = ['display_name' => 'Visitor', 'content' => 'Hello everyone', 'guest_key' => 'stable-guest-key-12345'];
        $message = $this->postJson("/api/v1/public/services/{$service->id}/chat", $guest)->assertCreated()->json('data');

        $this->actingAs($admin)->patchJson("/api/v1/staff/services/{$service->id}/chat/settings", ['paused' => false, 'slow_mode_seconds' => 30, 'reason' => 'High chat volume.'])
            ->assertOk()->assertJsonPath('data.settings.slow_mode_seconds', 30);
        auth()->logout();
        $this->postJson("/api/v1/public/services/{$service->id}/chat", [...$guest, 'content' => 'A second message'])->assertTooManyRequests();

        $this->actingAs($admin)->patchJson("/api/v1/staff/services/{$service->id}/chat/settings", ['paused' => true, 'slow_mode_seconds' => 30, 'reason' => 'Host announcement.'])
            ->assertOk()->assertJsonPath('data.settings.paused', true);
        auth()->logout();
        $this->postJson("/api/v1/public/services/{$service->id}/chat", ['display_name' => 'Another', 'content' => 'Can I post?', 'guest_key' => 'another-guest-key-123'])->assertConflict();
        $this->actingAs($admin)->postJson("/api/v1/public/services/{$service->id}/chat", ['display_name' => 'Online Host', 'content' => 'We will resume shortly.'])
            ->assertCreated()->assertJsonPath('data.is_host', true);

        $this->postJson("/api/v1/staff/services/{$service->id}/chat/{$message['id']}/mute", ['reason' => 'Repeated disruption.', 'duration_minutes' => 30])
            ->assertOk()->assertJsonPath('data.status', 'muted');
        $this->deleteJson("/api/v1/staff/services/{$service->id}/chat/{$message['id']}", ['reason' => 'Removed personal information.'])->assertNoContent();
        $this->patchJson("/api/v1/staff/services/{$service->id}/chat/settings", ['paused' => false, 'slow_mode_seconds' => 0])->assertOk();
        auth()->logout();
        $this->postJson("/api/v1/public/services/{$service->id}/chat", [...$guest, 'content' => 'Trying again'])->assertForbidden();

        $public = $this->getJson("/api/v1/public/services/{$service->id}/chat")->assertOk()->assertJsonPath('meta.paused', false);
        $this->assertFalse(collect($public->json('data'))->contains('id', $message['id']));
        $this->assertDatabaseHas('service_chat_moderation_events', ['service_occurrence_id' => $service->id, 'action' => 'participant_muted']);
        $this->assertDatabaseHas('service_chat_moderation_events', ['service_occurrence_id' => $service->id, 'action' => 'message_removed']);
    }

    public function test_moderation_endpoints_require_staff_permission(): void
    {
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'UTC', 'starts_at' => now(), 'state' => 'live']);
        $this->getJson("/api/v1/staff/services/{$service->id}/chat/moderation")->assertUnauthorized();
    }
}
