<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_transport_refuses_to_generate_recovery_link(): void
    {
        $user = $this->install();
        config(['mail.default' => 'log']);
        $this->postJson('/api/v1/auth/password/reset-request', ['email' => $user->email])->assertServiceUnavailable();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_unknown_email_has_same_success_response_and_no_token(): void
    {
        $this->install();
        config(['mail.default' => 'array']);
        Mail::fake();
        $this->postJson('/api/v1/auth/password/reset-request', ['email' => 'unknown@example.org'])
            ->assertOk()->assertJsonPath('data.status', 'requested');
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_known_email_creates_hashed_token_and_throttles_repeat_request(): void
    {
        $user = $this->install();
        config(['mail.default' => 'array']);
        Mail::fake();
        $this->postJson('/api/v1/auth/password/reset-request', ['email' => $user->email])
            ->assertOk()->assertJsonPath('data.status', 'requested');
        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);
        $this->assertStringStartsWith('$2', $record->token);
        $this->postJson('/api/v1/auth/password/reset-request', ['email' => $user->email])->assertOk();
        $this->assertSame($record->token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
    }

    public function test_reset_expires_consumes_token_and_revokes_only_owners_sessions(): void
    {
        $user = $this->install();
        $other = User::factory()->create();
        $token = bin2hex(random_bytes(32));
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()]);
        foreach ([[$user->id, 'owned'], [$other->id, 'other']] as [$userId, $sessionId]) {
            DB::table('sessions')->insert(['id' => $sessionId, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(31)]);
        $this->postJson('/api/v1/auth/password/reset', $this->payload($user->email, $token))
            ->assertUnprocessable();
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()]);
        $this->postJson('/api/v1/auth/password/reset', $this->payload($user->email, str_repeat('a', 64)))
            ->assertUnprocessable();
        $this->postJson('/api/v1/auth/password/reset', $this->payload($user->email, $token))
            ->assertOk();
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['id' => 'owned']);
        $this->assertDatabaseHas('sessions', ['id' => 'other']);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.password_reset', 'subject_id' => $user->id]);
        $this->postJson('/api/v1/auth/password/reset', $this->payload($user->email, $token))
            ->assertUnprocessable();
    }

    private function install(): User
    {
        return app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org',
            'timezone' => 'Africa/Lagos', 'location' => 'Main Campus',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
    }

    private function payload(string $email, string $token): array
    {
        return [
            'email' => $email, 'token' => $token,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ];
    }
}
