<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberPrayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_prayer_text_is_encrypted_and_visible_only_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->getJson('/api/v1/me/profile')->assertOk();
        $this->actingAs($other)->getJson('/api/v1/me/profile')->assertOk();

        $created = $this->actingAs($owner)->postJson('/api/v1/me/prayer-requests', [
            'prayer_text' => 'Please pray for wisdom.',
            'visibility' => 'assigned_care_only',
            'consent_to_contact' => false,
        ])->assertCreated();

        $stored = DB::table('prayer_requests')->where('id', $created->json('data.id'))->first();
        $this->assertNotSame('Please pray for wisdom.', $stored->prayer_text_encrypted);
        $this->actingAs($owner)->getJson('/api/v1/me/prayer-requests')->assertJsonPath('data.0.prayer_text', 'Please pray for wisdom.');
        $this->actingAs($other)->getJson('/api/v1/me/prayer-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->postJson("/api/v1/me/prayer-requests/{$stored->id}/removal-request")->assertNotFound();
    }
}
