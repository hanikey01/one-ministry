<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberGivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_only_sees_their_own_verified_gifts(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($member)->getJson('/api/v1/me/profile')->assertOk();
        $this->actingAs($other)->getJson('/api/v1/me/profile')->assertOk();
        $memberPerson = DB::table('people')->where('user_id', $member->id)->value('id');
        $otherPerson = DB::table('people')->where('user_id', $other->id)->value('id');
        $fund = (string) Str::uuid();
        DB::table('giving_funds')->insert(['id' => $fund, 'name' => 'General', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $own = (string) Str::uuid();
        $foreign = (string) Str::uuid();
        foreach ([[$own, $memberPerson, 2500], [$foreign, $otherPerson, 9000]] as [$id, $person, $amount]) {
            DB::table('donations')->insert(['id' => $id, 'fund_id' => $fund, 'person_id' => $person, 'amount_minor' => $amount, 'currency' => 'USD', 'provider' => 'test', 'status' => 'confirmed', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($member)->getJson('/api/v1/me/donations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $own)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['id' => $foreign]);
    }
}
