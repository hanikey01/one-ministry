<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function install(): User
    {
        return app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org',
            'timezone' => 'Africa/Lagos', 'location' => 'Main Campus',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
    }

    public function test_private_location_and_primary_switch_obey_public_visibility(): void
    {
        $admin = $this->install();
        $this->actingAs($admin);
        $first = DB::table('locations')->first();

        $response = $this->postJson('/api/v1/staff/locations', [
            'name' => 'Prayer Office', 'timezone' => 'Africa/Lagos',
            'is_public' => false, 'is_primary' => true,
        ])->assertCreated()->assertJsonPath('data.is_primary', true);
        $secondId = $response->json('data.id');
        $slug = $response->json('data.slug');

        $this->assertDatabaseHas('locations', ['id' => $first->id, 'is_primary' => false]);
        $this->assertDatabaseHas('locations', ['id' => $secondId, 'is_primary' => true]);
        $this->getJson('/api/v1/public/site')->assertJsonCount(1, 'data.locations')
            ->assertJsonMissing(['name' => 'Prayer Office']);
        $this->patchJson("/api/v1/staff/locations/{$secondId}", ['is_primary' => false])->assertStatus(409);

        $this->patchJson("/api/v1/staff/locations/{$first->id}", ['is_primary' => true])->assertOk();
        $this->patchJson("/api/v1/staff/locations/{$secondId}", ['name' => 'Private Prayer Office'])
            ->assertOk()->assertJsonPath('data.slug', $slug);
        $this->assertDatabaseHas('locations', ['id' => $first->id, 'is_primary' => true]);
        $this->assertDatabaseHas('locations', ['id' => $secondId, 'is_primary' => false]);
        $this->assertDatabaseCount('audit_events', 3);
    }

    public function test_feature_preview_does_not_publish_module_and_public_enable_is_blocked(): void
    {
        $admin = $this->install();
        $this->actingAs($admin);
        $this->patchJson('/api/v1/staff/features/digital_church', ['state' => 'staff_preview'])
            ->assertOk()->assertJsonPath('data.state', 'staff_preview');
        $this->getJson('/api/v1/public/site')->assertJsonPath('data.features.digital_church', 'disabled');
        $this->patchJson('/api/v1/staff/features/digital_church', ['state' => 'enabled'])->assertOk()->assertJsonPath('data.state', 'enabled');
        $this->assertDatabaseHas('feature_settings', ['key' => 'digital_church', 'state' => 'enabled']);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_direct_administration_calls_require_the_correct_permission(): void
    {
        $this->install();
        $this->getJson('/api/v1/staff/locations')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/staff/locations')->assertForbidden();
        $this->postJson('/api/v1/staff/locations', ['name' => 'East', 'timezone' => 'Africa/Lagos'])->assertForbidden();
        $this->patchJson('/api/v1/staff/features/giving', ['state' => 'staff_preview'])->assertForbidden();
    }
}
