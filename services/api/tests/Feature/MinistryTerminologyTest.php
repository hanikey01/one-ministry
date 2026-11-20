<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\MinistrySetting;
use App\Support\MinistryTerminology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryTerminologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminology_page_covers_current_platform_workflows(): void
    {
        $admin = $this->install();

        $this->actingAs($admin)->get('/staff/terminology')
            ->assertOk()
            ->assertSee('Platform terms')
            ->assertSee('Person / People')
            ->assertSee('Event / Events')
            ->assertSee('Next step / Next steps')
            ->assertSee('Testimony / Testimonies');
    }

    public function test_updated_platform_terms_are_saved_displayed_and_used_by_navigation(): void
    {
        $admin = $this->install();
        $terms = collect(MinistryTerminology::TERMS)
            ->map(fn (array $definition): array => [
                'singular' => $definition['singular'],
                'plural' => $definition['plural'],
            ])
            ->all();
        $terms['person'] = ['singular' => 'Participant', 'plural' => 'Participants'];
        $terms['event'] = ['singular' => 'Gathering', 'plural' => 'Gatherings'];
        $terms['next_step'] = ['singular' => 'Response', 'plural' => 'Responses'];
        $terms['testimony'] = ['singular' => 'Story', 'plural' => 'Stories'];

        $this->actingAs($admin)->put('/staff/terminology', ['terminology' => $terms])
            ->assertRedirect(route('staff.terminology'));

        $settings = MinistrySetting::query()->findOrFail(MinistrySetting::SINGLETON_ID);
        $this->assertSame('Participants', $settings->terminology['person']['plural']);
        $this->assertSame('Gatherings', $settings->terminology['event']['plural']);
        $this->assertSame('Responses', $settings->terminology['next_step']['plural']);
        $this->assertSame('Stories', $settings->terminology['testimony']['plural']);
        $this->assertSame('Gatherings', app(MinistryTerminology::class)->navigationLabel('/events', 'Events'));
        $this->assertSame('Stories', app(MinistryTerminology::class)->navigationLabel('/testimonies', 'Testimonies'));

        $this->actingAs($admin)->get('/staff/terminology')
            ->assertOk()
            ->assertSee('value="Gatherings"', false)
            ->assertSee('value="Stories"', false);
    }

    public function test_department_terminology_is_used_by_the_public_directory_and_member_workspace(): void
    {
        $admin = $this->install();
        $terms = collect(MinistryTerminology::TERMS)
            ->map(fn (array $definition): array => [
                'singular' => $definition['singular'],
                'plural' => $definition['plural'],
            ])
            ->all();
        $terms['department'] = ['singular' => 'Unit', 'plural' => 'Units'];

        $this->actingAs($admin)->put('/staff/terminology', ['terminology' => $terms])
            ->assertRedirect(route('staff.terminology'));

        $this->get('/departments')
            ->assertOk()
            ->assertSee('<title>Units', false)
            ->assertSee('No public units are available yet.');

        $this->actingAs($admin)->get('/member/departments')
            ->assertOk()
            ->assertSee('<title>My Units', false)
            ->assertSee('You have not joined a unit');
    }

    public function test_contact_page_renders_for_a_fresh_installation(): void
    {
        $this->install();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('We would love to hear from you.')
            ->assertSee('Send a message');
    }

    private function install(): object
    {
        return app(InstallMinistry::class)->execute([
            'name' => 'Hope Community',
            'domain' => 'hope.example.org',
            'timezone' => 'UTC',
            'location' => 'Main',
            'admin_name' => 'Ada Admin',
            'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
    }
}
