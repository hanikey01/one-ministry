<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    use RefreshDatabase;

    private function installationInput(): array
    {
        return [
            'name' => 'Hope Community',
            'domain' => 'hope.example.org',
            'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus',
            'tagline' => 'A place to belong',
            'contact_email' => 'hello@example.org',
            'primary_color' => '#17324D',
            'accent_color' => '#D9A441',
            'admin_name' => 'Ada Admin',
            'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ];
    }

    public function test_public_site_requires_installation(): void
    {
        $this->getJson('/api/v1/public/site')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'installation_required');
    }

    public function test_installation_creates_one_ministry_and_a_private_administrator(): void
    {
        $admin = app(InstallMinistry::class)->execute($this->installationInput());

        $this->assertDatabaseCount('ministry_settings', 1);
        $this->assertSame(MinistrySetting::SINGLETON_ID, MinistrySetting::firstOrFail()->id);
        $this->assertNotSame('long-private-password', $admin->password);
        $this->assertDatabaseHas('user_role_assignments', ['user_id' => $admin->id, 'role_key' => 'security_administrator']);
        $this->assertDatabaseHas('role_permissions', ['role_key' => 'ministry_administrator', 'permission_key' => 'services.manage']);
        $this->assertDatabaseHas('feature_settings', ['key' => 'digital_church', 'state' => 'enabled']);
        $this->assertSame(0, FeatureSetting::query()->where('state', '!=', 'enabled')->count());

        Location::create(['name' => 'Private Office', 'slug' => 'private-office', 'timezone' => 'Africa/Lagos', 'is_public' => false]);
        FeatureSetting::where('key', 'digital_church')->update(['state' => 'staff_preview']);

        $response = $this->getJson('/api/v1/public/site')->assertOk()
            ->assertJsonPath('data.name', 'Hope Community')
            ->assertJsonPath('data.tagline', 'A place to belong')
            ->assertJsonPath('data.features.digital_church', 'disabled')
            ->assertJsonCount(1, 'data.locations');

        $body = $response->getContent();
        $this->assertStringNotContainsString('Private Office', $body);
        $this->assertStringNotContainsString('ada@example.org', $body);
        $this->assertStringNotContainsString('long-private-password', $body);
    }

    public function test_installation_cannot_be_run_twice(): void
    {
        $installer = app(InstallMinistry::class);
        $installer->execute($this->installationInput());

        $this->expectException(ValidationException::class);
        $installer->execute($this->installationInput());
    }

    public function test_browser_setup_configures_an_uninstalled_ministry_once(): void
    {
        $response = $this->postJson('/api/v1/installation/setup', $this->installationInput());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'configured')
            ->assertJsonPath('data.admin_email', 'ada@example.org');

        $this->postJson('/api/v1/installation/setup', $this->installationInput())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installation');
    }
}
