<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiModelSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_administrator_can_save_an_encrypted_provider_key(): void
    {
        $admin = $this->installAdministrator();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($admin)->putJson('/staff/ai-settings/providers/gemini', [
            'api_key' => 'gemini-private-key',
            'enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.provider_key', 'gemini')
            ->assertJsonPath('data.provider.configured', true)
            ->assertJsonPath('data.provider.enabled', true)
            ->assertJsonPath('data.redirect', route('staff.ai-settings'))
            ->assertJsonMissing(['api_key' => 'gemini-private-key']);

        $response->assertSessionHas('status', 'AI provider settings saved. Refresh its model catalog to verify the connection.');

        $connection = DB::table('ai_provider_connections')->where('provider_key', 'gemini')->first();
        $this->assertNotNull($connection);
        $this->assertNotSame('gemini-private-key', $connection->api_key_encrypted);
        $this->assertSame('gemini-private-key', Crypt::decryptString($connection->api_key_encrypted));
        $this->assertDatabaseHas('ai_configuration_audits', [
            'actor_user_id' => $admin->id,
            'action' => 'ai.provider.updated',
            'subject_id' => 'gemini',
        ]);
    }

    public function test_saved_key_can_be_replaced_and_removed_without_being_returned(): void
    {
        $admin = $this->installAdministrator();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->putJson('/staff/ai-settings/providers/openrouter', [
            'api_key' => 'first-private-key',
            'enabled' => true,
        ])->assertOk();

        $replacement = $this->actingAs($admin)->putJson('/staff/ai-settings/providers/openrouter', [
            'api_key' => 'replacement-private-key',
            'enabled' => true,
        ])->assertOk();
        $replacement->assertJsonMissing(['api_key' => 'replacement-private-key']);

        $encrypted = DB::table('ai_provider_connections')->where('provider_key', 'openrouter')->value('api_key_encrypted');
        $this->assertSame('replacement-private-key', Crypt::decryptString($encrypted));

        $this->actingAs($admin)->putJson('/staff/ai-settings/providers/openrouter', [
            'clear_api_key' => true,
        ])->assertOk()->assertJsonPath('data.provider.configured', false);

        $this->assertNull(DB::table('ai_provider_connections')->where('provider_key', 'openrouter')->value('api_key_encrypted'));
    }

    public function test_unverified_administrator_receives_the_actionable_verification_error(): void
    {
        $admin = $this->installAdministrator();

        $this->actingAs($admin)->putJson('/staff/ai-settings/providers/gemini', [
            'api_key' => 'must-not-be-saved',
            'enabled' => true,
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'email_verification_required')
            ->assertJsonPath('error.message', 'Verify your email before using this action.');

        $this->assertDatabaseMissing('ai_provider_connections', ['provider_key' => 'gemini']);
    }

    public function test_enabled_provider_catalog_can_be_loaded_by_the_model_chooser(): void
    {
        $admin = $this->installAdministrator();
        $admin->forceFill(['email_verified_at' => now()])->save();

        DB::table('ai_provider_connections')->insert([
            'provider_key' => 'gemini',
            'api_key_encrypted' => Crypt::encryptString('gemini-private-key'),
            'enabled' => true,
            'connection_status' => 'connected',
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_model_catalog')->insert([
            'id' => (string) Str::uuid(),
            'provider_key' => 'gemini',
            'model_id' => 'gemini-structured-test',
            'display_name' => 'Gemini Structured Test',
            'supports_structured_output' => true,
            'input_modalities' => json_encode(['text']),
            'output_modalities' => json_encode(['text']),
            'availability' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/staff/ai-settings/models')
            ->assertOk()
            ->assertJsonPath('data.models.0.provider_key', 'gemini')
            ->assertJsonPath('data.models.0.model_id', 'gemini-structured-test')
            ->assertJsonPath('data.has_more', false);
    }

    private function installAdministrator()
    {
        return app(InstallMinistry::class)->execute([
            'name' => 'Hope Community',
            'domain' => 'hope.example.org',
            'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus',
            'admin_name' => 'Ada Admin',
            'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
    }
}
