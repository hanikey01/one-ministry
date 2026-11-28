<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Services\EmailDeliverySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberRegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_sends_a_code_and_opens_the_verification_step(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $this->mock(EmailDeliverySettingsService::class)->shouldReceive('sendHtml')->once();

        $this->post('/register', ['name' => 'New Member', 'email' => 'new@example.org'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('member_registration.email', 'new@example.org');

        $this->assertDatabaseHas('member_registration_verifications', ['email' => 'new@example.org']);
    }

    public function test_signup_mail_failure_returns_to_the_form_without_leaving_a_pending_code(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $this->mock(EmailDeliverySettingsService::class)->shouldReceive('sendHtml')->once()->andThrow(new \RuntimeException('Transport unavailable'));

        $this->from('/register')->post('/register', ['name' => 'New Member', 'email' => 'new@example.org'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('member_registration_verifications', ['email' => 'new@example.org']);
    }

    public function test_member_account_is_created_only_after_the_email_code_is_verified(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $id = (string) Str::uuid();
        DB::table('member_registration_verifications')->insert(['id' => $id, 'email' => 'new@example.org', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'attempts' => 0, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $session = ['member_registration' => ['id' => $id, 'name' => 'New Member', 'email' => 'new@example.org']];

        $this->withSession($session)->post('/register/verify', ['code' => '123456'])->assertRedirect(route('register.complete'));
        $this->assertDatabaseHas('member_registration_verifications', ['id' => $id]);

        $this->withSession(['member_registration' => [...$session['member_registration'], 'verified' => true]])
            ->post('/register/complete', ['password' => 'a-secure-member-password', 'password_confirmation' => 'a-secure-member-password', 'terms' => '1'])
            ->assertRedirect(route('member.home'));

        $this->assertDatabaseHas('users', ['email' => 'new@example.org']);
        $this->assertDatabaseMissing('member_registration_verifications', ['id' => $id]);
    }

    public function test_incorrect_code_does_not_create_an_account(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $id = (string) Str::uuid();
        DB::table('member_registration_verifications')->insert(['id' => $id, 'email' => 'new@example.org', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'attempts' => 0, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->withSession(['member_registration' => ['id' => $id, 'name' => 'New Member', 'email' => 'new@example.org']])
            ->post('/register/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('member_registration_verifications', ['id' => $id, 'attempts' => 1]);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.org']);
    }
}
