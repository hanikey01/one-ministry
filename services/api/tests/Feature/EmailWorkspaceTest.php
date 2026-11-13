<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use App\Services\EmailDeliverySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_email_workspace_and_edit_a_valid_template(): void
    {
        $admin = $this->install();
        $this->actingAs($admin)->get('/staff/email')->assertOk()->assertSee('Delivery provider')->assertSee('Staff email verification');
        $this->actingAs($admin)->put('/staff/email/templates/password_reset', ['subject' => 'Reset access for {{ user_name }}', 'body_template' => 'Use the secure button within {{ expires_minutes }} minutes.', 'enabled' => '1'])->assertRedirect(route('staff.email', ['template' => 'password_reset']));
        $this->assertDatabaseHas('email_templates', ['key' => 'password_reset', 'subject' => 'Reset access for {{ user_name }}']);
        $this->assertDatabaseHas('audit_events', ['action' => 'email_template.updated']);
    }

    public function test_unknown_template_placeholder_is_rejected(): void
    {
        $admin = $this->install();
        $this->actingAs($admin)->get('/staff/email')->assertOk();
        $this->actingAs($admin)->from('/staff/email')->put('/staff/email/templates/password_reset', ['subject' => 'Reset {{ private_token }}', 'body_template' => 'Hello {{ user_name }}', 'enabled' => '1'])->assertSessionHasErrors('body_template');
    }

    public function test_saved_delivery_connection_can_send_a_test_and_records_success(): void
    {
        Mail::fake();
        $admin = $this->install();
        app(EmailDeliverySettingsService::class)->update([
            'provider' => 'custom_smtp',
            'enabled' => true,
            'from_address' => 'hello@hope.example.org',
            'from_name' => 'Hope',
            'host' => 'smtp.example.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'password' => 'smtp-password',
        ], $admin, '127.0.0.1');
        $this->actingAs($admin)->post('/staff/email/delivery/test', ['test_email' => 'admin@example.org'])->assertRedirect(route('staff.email'));
        $this->assertDatabaseHas('email_delivery_settings', ['last_test_status' => 'succeeded']);
        $this->assertDatabaseHas('audit_events', ['action' => 'email_delivery.tested']);
    }

    public function test_security_page_sends_administrator_to_the_email_workspace_when_delivery_is_inactive(): void
    {
        $admin = $this->install();

        $this->actingAs($admin)->get('/staff/security')
            ->assertOk()
            ->assertSee('href="'.route('staff.email').'"', false)
            ->assertDontSee('staff/settings#email-delivery', false);

        $this->actingAs($admin)->post('/staff/security/email-verification')
            ->assertRedirect(route('staff.email'));
    }

    public function test_security_page_does_not_offer_email_configuration_to_user_without_permission(): void
    {
        $this->install();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/staff/security')
            ->assertOk()
            ->assertSee('Ask a ministry administrator to activate email delivery')
            ->assertDontSee('Set up email delivery');

        $this->actingAs($user)->post('/staff/security/email-verification')
            ->assertRedirect(route('staff.security'))
            ->assertSessionHas('security_notice');
    }

    private function install()
    {
        return app(InstallMinistry::class)->execute(['name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password']);
    }
}
