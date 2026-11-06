<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NextStepRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_next_step_creates_one_generic_follow_up_request(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->from('/member/next-steps')->post('/member/next-steps', ['type' => 'baptism', 'consent_to_contact' => false])
            ->assertRedirect('/member/next-steps')->assertSessionHasErrors('consent_to_contact');

        $this->actingAs($member)->post('/member/next-steps', ['type' => 'baptism', 'consent_to_contact' => true])
            ->assertRedirect(route('member.next-steps'));
        $this->assertDatabaseHas('follow_up_tasks', ['kind' => 'next_step', 'request_key' => 'baptism', 'status' => 'open']);
        $this->assertDatabaseCount('follow_up_tasks', 1);

        $this->actingAs($member)->from('/member/next-steps')->post('/member/next-steps', ['type' => 'baptism', 'consent_to_contact' => true])
            ->assertRedirect('/member/next-steps')->assertSessionHasErrors('type');
        $this->assertSame(1, DB::table('follow_up_tasks')->where('kind', 'next_step')->count());
    }
}
