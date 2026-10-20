<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Services\EmailDeliverySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_transport_refuses_verification_token_and_valid_link_is_one_time(): void
    {
        $user = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        config(['mail.default' => 'log']);
        $this->actingAs($user)->postJson('/api/v1/auth/email/verification-request')->assertServiceUnavailable();
        $token = bin2hex(random_bytes(32));
        DB::table('email_verification_tokens')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/api/v1/auth/email/verify', ['token' => $token])->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.email_verified', 'subject_id' => $user->id]);
        $this->postJson('/api/v1/auth/email/verify', ['token' => $token])->assertUnprocessable();
    }

    public function test_delivery_settings_encrypt_smtp_credentials_and_never_audit_them(): void
    {
        $user = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $setting = app(EmailDeliverySettingsService::class)->update([
            'provider' => 'resend', 'enabled' => true, 'from_address' => 'hello@hope.example.org', 'from_name' => 'Hope',
            'host' => null, 'port' => null, 'encryption' => null, 'username' => 'resend', 'password' => 'private-provider-key',
        ], $user, '127.0.0.1');

        $this->assertTrue($setting->enabled);
        $this->assertNotSame('private-provider-key', $setting->password_encrypted);
        $this->assertDatabaseHas('email_delivery_settings', ['provider' => 'resend', 'enabled' => true]);
        $this->assertStringNotContainsString('private-provider-key', (string) DB::table('audit_events')->where('action', 'email_delivery_settings.updated')->value('changes_json'));
    }
}
