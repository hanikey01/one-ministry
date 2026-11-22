<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PublicSubmissionGuard;
use App\Models\User;
use Tests\TestCase;

class PublicConnectionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        DB::table('next_step_options')->insert(['id' => (string) Str::uuid(), 'key' => 'baptism', 'label' => 'Baptism', 'active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_guest_can_submit_a_next_step_without_an_account(): void
    {
        $this->get('/member/next-steps')->assertOk();
        $this->post('/member/next-steps', [...$this->guard('member.next-steps.request'), 'first_name' => 'Grace', 'last_name' => 'Guest', 'email' => 'grace@example.org', 'type' => 'baptism', 'consent_to_contact' => '1'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('people', ['email' => 'grace@example.org', 'first_name' => 'Grace', 'consent_to_contact' => true]);
        $this->assertDatabaseHas('follow_up_tasks', ['kind' => 'next_step', 'request_key' => 'baptism', 'status' => 'open']);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => null, 'action' => 'next_step.request_submitted']);
        $this->assertDatabaseMissing('users', ['email' => 'grace@example.org']);
    }

    public function test_guest_prayer_is_encrypted_and_not_bound_to_a_user_account(): void
    {
        $this->get('/member/prayer')->assertOk();
        $this->post('/member/prayer', [...$this->guard('member.prayer'), 'first_name' => 'Grace', 'email' => 'grace@example.org', 'prayer_text' => 'Please pray for my family as we make a difficult decision.', 'consent_to_contact' => '1'])
            ->assertRedirect()->assertSessionHas('status');

        $request = DB::table('prayer_requests')->first();
        $this->assertNull($request->user_id);
        $this->assertSame('prayer_team', $request->visibility);
        $this->assertSame('Please pray for my family as we make a difficult decision.', Crypt::decryptString($request->prayer_text_encrypted));
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => null, 'action' => 'prayer.request_submitted']);
    }

    public function test_guest_testimony_requires_contact_and_publication_consent(): void
    {
        DB::table('feature_settings')->updateOrInsert(['key' => 'testimonies'], ['state' => 'enabled', 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/member/testimonies')->assertOk();
        $this->post('/member/testimonies', [...$this->guard('member.testimonies.store'), 'first_name' => 'Grace', 'email' => 'grace@example.org', 'title' => 'A new beginning', 'category' => 'general', 'body' => 'This is a sufficiently long testimony that has been shared for careful staff review.', 'consent_to_contact' => '1'])
            ->assertSessionHasErrors('consent_public');

        $this->post('/member/testimonies', [...$this->guard('member.testimonies.store'), 'first_name' => 'Grace', 'email' => 'grace@example.org', 'title' => 'A new beginning', 'category' => 'general', 'body' => 'This is a sufficiently long testimony that has been shared for careful staff review.', 'consent_to_contact' => '1', 'consent_public' => '1'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('testimonies', ['title' => 'A new beginning', 'status' => 'submitted', 'consent_public' => true]);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => null, 'action' => 'testimony.member_submitted']);
    }

    public function test_guest_email_matching_an_account_files_the_request_under_that_person_without_authenticating_it(): void
    {
        $member = User::factory()->create(['name' => 'Registered Member', 'email' => 'member@example.org']);

        $this->post('/member/next-steps', [...$this->guard('member.next-steps.request'), 'first_name' => 'Typed Name', 'email' => 'MEMBER@example.org', 'type' => 'baptism', 'consent_to_contact' => '1'])
            ->assertRedirect()->assertSessionHas('status');

        $person = DB::table('people')->where('user_id', $member->id)->first();
        $this->assertNotNull($person);
        $this->assertSame('member@example.org', $person->email);
        $this->assertFalse((bool) $person->consent_to_contact);
        $this->assertDatabaseHas('follow_up_tasks', ['person_id' => $person->id, 'kind' => 'next_step', 'request_key' => 'baptism']);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => null, 'action' => 'next_step.request_submitted']);
    }

    private function guard(string $route): array
    {
        $token = app(PublicSubmissionGuard::class)->token($route);
        $this->travel(3)->seconds();

        return ['_form_started_at' => $token, 'website' => ''];
    }
}
