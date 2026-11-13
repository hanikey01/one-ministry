<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceFeatureHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_installation_keeps_attendance_disabled(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);

        $this->assertDatabaseHas('feature_settings', ['key' => 'attendance', 'state' => 'disabled']);
    }

    public function test_disabled_attendance_routes_are_unavailable_even_when_called_directly(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);

        $user = User::query()->where('email', 'ada@example.org')->firstOrFail();
        $this->actingAs($user)->get('/member/activity')->assertNotFound();
        $this->actingAs($user)->postJson('/api/v1/staff/services/'.Str::uuid().'/presence', [])->assertNotFound();
    }
}
