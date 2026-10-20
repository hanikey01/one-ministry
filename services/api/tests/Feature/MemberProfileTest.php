<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_manage_their_profile_contact_permission_and_month_day_birthday(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->patchJson('/api/v1/me/profile', [
            'first_name' => 'Ada',
            'preferred_name' => 'A.',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+15551234567',
            'consent_to_contact' => true,
            'birthday_collection_consent' => true,
            'birthday_month' => 2,
            'birthday_day' => 29,
        ])->assertOk();

        $person = DB::table('people')->where('user_id', $member->id)->first();
        $this->assertSame('A.', $person->preferred_name);
        $this->assertSame('2000-02-29 00:00:00', (string) $person->birthday);
        $this->assertTrue((bool) $person->consent_to_contact);

        $this->actingAs($member)->patchJson('/api/v1/me/profile', [
            'first_name' => 'Ada',
            'birthday_collection_consent' => false,
        ])->assertOk();

        $this->assertNull(DB::table('people')->where('id', $person->id)->value('birthday'));
    }
}
