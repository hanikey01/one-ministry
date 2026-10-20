<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\ServiceChatMessage;
use App\Models\ServiceOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_chat_only_accepts_messages_during_live_service(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'enabled']);
        $service = ServiceOccurrence::create(['title' => 'Sunday', 'timezone' => 'Africa/Lagos', 'starts_at' => now(), 'state' => 'scheduled']);
        $this->postJson("/api/v1/public/services/{$service->id}/chat", ['display_name' => 'Guest', 'content' => 'Hello', 'guest_key' => 'guest-key-for-testing'])->assertUnprocessable();
        $service->update(['state' => 'live']);
        $this->postJson("/api/v1/public/services/{$service->id}/chat", ['display_name' => 'Guest', 'content' => 'Hello', 'guest_key' => 'guest-key-for-testing'])->assertCreated();
        $message = ServiceChatMessage::firstOrFail();
        $this->getJson("/api/v1/public/services/{$service->id}/chat")->assertOk()->assertJsonCount(1, 'data');
        $this->assertNull($message->user_id);
    }
}
