<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'theme_preset' => 'tech_blue',
            'chrome_mode' => 'preset',
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

    public function test_installation_ingests_default_artwork_into_the_media_library_and_rewrites_default_pages(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        app(InstallMinistry::class)->execute($this->installationInput());

        $assets = DB::table('media_assets')->where('library', 'media_library')->where('provider', 'local')->get();
        $this->assertCount(13, $assets);
        foreach ($assets as $asset) {
            $this->assertSame('published', $asset->status);
            $this->assertStringStartsWith('media-library/defaults/', $asset->file_path);
            $this->assertSame('public', $asset->file_disk);
            Storage::disk('public')->assertExists($asset->file_path);
        }

        $homepage = DB::table('pages')->where('source_key', 'homepage')->value('blocks');
        $about = DB::table('pages')->where('source_key', 'about')->value('blocks');
        $this->assertStringContainsString('/storage/media-library/defaults/', $homepage);
        $this->assertStringContainsString('/storage/media-library/defaults/', $about);
        $this->assertStringNotContainsString('/images/pages/', $homepage);
        $this->assertStringNotContainsString('/images/pages/', $about);

        $this->get(route('public.media', $assets->first()->id))->assertOk();
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

    public function test_setup_wizard_requires_and_stores_complete_primary_location_details(): void
    {
        $input = $this->installationInput() + [
            'location_address_line_1' => '12 Unity Avenue',
            'location_address_line_2' => 'Community Hall',
            'location_city' => 'Lagos',
            'location_region_code' => 'LA',
            'location_postal_code' => '100001',
            'location_country_code' => 'NG',
        ];

        $this->post(route('setup.store'), $input)->assertRedirect(route('staff.overview'));

        $this->assertDatabaseHas('locations', [
            'name' => 'Main Campus',
            'address' => "12 Unity Avenue\nCommunity Hall\nLagos, Lagos, 100001\nNigeria",
            'address_line_1' => '12 Unity Avenue',
            'address_line_2' => 'Community Hall',
            'locality' => 'Lagos',
            'region_code' => 'LA',
            'region_name' => 'Lagos',
            'postal_code' => '100001',
            'country_code' => 'NG',
            'country_name' => 'Nigeria',
            'is_primary' => true,
        ]);
    }

    public function test_setup_wizard_stores_a_custom_ministry_colour(): void
    {
        $input = $this->installationInput() + [
            'location_address_line_1' => '12 Unity Avenue',
            'location_city' => 'Lagos',
            'location_country_code' => 'NG',
            'theme_preset' => 'neutral',
            'chrome_mode' => 'custom',
            'chrome_color' => '#c2410c',
        ];

        $this->post(route('setup.store'), $input)->assertRedirect(route('staff.overview'));

        $this->assertDatabaseHas('ministry_settings', [
            'theme_preset' => 'neutral',
            'chrome_mode' => 'custom',
            'chrome_color' => '#C2410C',
        ]);
    }

    public function test_setup_wizard_requires_a_valid_colour_when_custom_mode_is_selected(): void
    {
        $input = $this->installationInput() + [
            'location_address_line_1' => '12 Unity Avenue',
            'location_city' => 'Lagos',
            'location_country_code' => 'NG',
            'theme_preset' => 'tech_blue',
            'chrome_mode' => 'custom',
            'chrome_color' => 'orange',
        ];

        $this->from(route('setup.create'))->post(route('setup.store'), $input)
            ->assertRedirect(route('setup.create'))
            ->assertSessionHasErrors('chrome_color');

        $this->assertDatabaseCount('ministry_settings', 0);
    }

    public function test_setup_wizard_rejects_an_address_title_without_location_details(): void
    {
        $this->from(route('setup.create'))->post(route('setup.store'), $this->installationInput())
            ->assertRedirect(route('setup.create'))
            ->assertSessionHasErrors(['location_address_line_1', 'location_city', 'location_country_code']);

        $this->assertDatabaseCount('ministry_settings', 0);
    }
}
