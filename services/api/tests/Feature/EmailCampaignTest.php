<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Services\EmailCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_opted_in_people_with_an_email_are_counted_for_campaigns(): void
    {
        app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
        $personId = (string) Str::uuid();
        DB::table('people')->insert(['id' => $personId, 'first_name' => 'Email', 'last_name' => 'Recipient', 'email' => 'recipient@example.org', 'status' => 'member', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('notification_preferences')->insert(['id' => (string) Str::uuid(), 'person_id' => $personId, 'email_marketing' => true, 'sms_marketing' => false, 'birthday_staff_reminder' => false, 'birthday_public_recognition' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('people')->insert(['id' => (string) Str::uuid(), 'first_name' => 'No', 'last_name' => 'Consent', 'email' => 'no-consent@example.org', 'status' => 'member', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(1, app(EmailCampaignService::class)->eligibleRecipientCount());
    }
}
