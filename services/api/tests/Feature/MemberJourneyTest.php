<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_journey_requires_consent_creates_one_follow_up_and_remains_owner_scoped(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($member)->getJson('/api/v1/me/profile')->assertOk();
        $this->actingAs($other)->getJson('/api/v1/me/profile')->assertOk();
        $this->actingAs($member)->postJson('/api/v1/me/journeys', ['type' => 'baptism', 'consent_to_contact' => false])->assertUnprocessable();
        $this->actingAs($member)->postJson('/api/v1/me/journeys', ['type' => 'baptism', 'consent_to_contact' => true])->assertCreated();
        $this->actingAs($member)->postJson('/api/v1/me/journeys', ['type' => 'baptism', 'consent_to_contact' => true])->assertConflict();
        $this->assertDatabaseCount('member_journeys', 1);
        $this->assertSame(1, DB::table('follow_up_tasks')->where('kind', 'journey_baptism')->count());
        $this->actingAs($other)->getJson('/api/v1/me/journeys')->assertOk()->assertJsonCount(0, 'data');
    }
}
