<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_actions_require_enrolled_and_recent_mfa(): void
    {
        $admin = app(InstallMinistry::class)->execute(['name' => 'Hope', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos', 'location' => 'Main', 'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $this->actingAs($admin)->postJson('/api/v1/staff/invitations', ['name' => 'Eli', 'email' => 'eli@example.org'])->assertForbidden()->assertJsonPath('error.code', 'mfa_required');
        $this->actingAs($admin)->postJson('/api/v1/auth/mfa/setup')->assertOk();
        $secret = Crypt::decryptString(DB::table('mfa_credentials')->where('user_id', $admin->id)->value('secret'));
        $method = new \ReflectionMethod(MfaService::class, 'code');
        $method->setAccessible(true);
        $code = $method->invoke(app(MfaService::class), $secret, intdiv(time(), 30));
        $response = $this->postJson('/api/v1/auth/mfa/enable', ['code' => $code])->assertOk();
        $this->assertCount(8, $response->json('data.recovery_codes'));
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->postJson('/api/v1/staff/invitations', ['name' => 'Eli', 'email' => 'eli@example.org'])->assertCreated();
        $this->assertDatabaseHas('audit_events', ['action' => 'mfa.enabled', 'subject_id' => $admin->id]);
    }
}
