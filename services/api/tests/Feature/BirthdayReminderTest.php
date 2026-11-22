<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use App\Services\EmailTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BirthdayReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_uses_the_ministry_timezone_and_does_not_send_duplicates(): void
    {
        CarbonImmutable::setTestNow('2026-07-02 03:30:00 UTC');
        $this->install('America/New_York');
        $recipient = $this->staff('ministry_operations_coordinator');
        $this->person('Grace', '2000-07-02 00:00:00');

        $templates = $this->mock(EmailTemplateService::class);
        $templates->shouldReceive('send')->once()->withArgs(fn ($key, $email, $variables) => $key === 'birthday_staff_reminder'
            && $email === $recipient->email
            && $variables['birthday_date'] === 'July 2');

        $this->artisan('birthdays:send-reminders --days=1')->assertSuccessful();
        $this->artisan('birthdays:send-reminders --days=1')->assertSuccessful();

        $this->assertDatabaseCount('birthday_reminder_deliveries', 1);
        $this->assertDatabaseHas('birthday_reminder_deliveries', ['status' => 'sent', 'birthday_year' => 2026]);
    }

    public function test_it_skips_missing_and_inactive_birthdays_and_unauthorized_staff(): void
    {
        CarbonImmutable::setTestNow('2026-05-09 12:00:00 UTC');
        $this->install('UTC');
        $this->staff('content_media_editor');
        $this->person('No birthday', null);
        $this->person('Inactive', '2000-05-10 00:00:00', 'inactive');

        $this->mock(EmailTemplateService::class)->shouldNotReceive('send');
        $this->artisan('birthdays:send-reminders --days=1')->assertSuccessful();

        $this->assertDatabaseCount('birthday_reminder_deliveries', 0);
    }

    public function test_leap_day_birthdays_are_observed_on_february_28_in_a_non_leap_year(): void
    {
        CarbonImmutable::setTestNow('2027-02-27 09:00:00 UTC');
        $this->install('UTC');
        $this->staff('ministry_operations_coordinator');
        $this->person('Leap Member', '2000-02-29 00:00:00');

        $this->mock(EmailTemplateService::class)->shouldReceive('send')->once();
        $this->artisan('birthdays:send-reminders --days=1')->assertSuccessful();
    }

    private function install(string $timezone): User
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => $timezone, 'location' => 'Main',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('user_role_assignments')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_key' => $role,
            'scope_type' => 'installation', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function person(string $name, ?string $birthday, string $status = 'member'): void
    {
        DB::table('people')->insert([
            'id' => (string) Str::uuid(), 'first_name' => $name, 'birthday' => $birthday,
            'status' => $status, 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
