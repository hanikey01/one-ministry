<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_manage_their_profile_contact_permission_and_month_day_birthday(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->patchJson('/api/v1/me/profile', [
            'first_name' => 'Ada',
            'preferred_name' => 'A.',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+15551234567',
            'consent_to_contact' => true,
            'birthday_collection_consent' => true,
            'birthday_month' => 2,
            'birthday_day' => 29,
        ])->assertOk();

        $person = DB::table('people')->where('user_id', $member->id)->first();
        $this->assertSame('A.', $person->preferred_name);
        $this->assertSame('2000-02-29 00:00:00', (string) $person->birthday);
        $this->assertTrue((bool) $person->consent_to_contact);

        $this->actingAs($member)->patchJson('/api/v1/me/profile', [
            'first_name' => 'Ada',
            'birthday_collection_consent' => false,
        ])->assertOk();

        $this->assertNull(DB::table('people')->where('id', $person->id)->value('birthday'));
    }

    public function test_member_profile_photo_is_replaced_safely_and_publicly_delivered_without_a_storage_link(): void
    {
        Storage::fake('public');
        $member = User::factory()->create(['name' => 'Ada Lovelace']);
        $personId = (string) Str::uuid();
        $previousPath = 'profiles/previous.png';
        Storage::disk('public')->put($previousPath, 'previous image');
        DB::table('people')->insert([
            'id' => $personId,
            'user_id' => $member->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $member->email,
            'status' => 'member',
            'consent_to_contact' => false,
            'profile_image_path' => $previousPath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2ZQAAAABJRU5ErkJggg==', true);

        $this->actingAs($member)->put('/member', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $member->email,
            'birthday_month' => 12,
            'birthday_day' => 10,
            'profile_image' => UploadedFile::fake()->createWithContent('profile.png', $png),
        ])->assertRedirect(route('member.profile'));

        $storedPath = DB::table('people')->where('id', $personId)->value('profile_image_path');
        $this->assertIsString($storedPath);
        $this->assertNotSame($previousPath, $storedPath);
        Storage::disk('public')->assertExists($storedPath);
        Storage::disk('public')->assertMissing($previousPath);

        $this->get(route('public.storage', ['path' => $storedPath]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_member_profile_uses_profile_name_and_has_no_birthday_opt_in_fields(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/member/profile')
            ->assertOk()
            ->assertSee('<h1>Profile</h1>', false)
            ->assertDontSee('Profile & preferences')
            ->assertDontSee('birthday_staff_reminder')
            ->assertDontSee('birthday_public_recognition');
    }

}
