<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ServiceReminderContractTest extends TestCase
{
    public function test_member_reminders_have_durable_subscription_and_delivery_records(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_11_27_000088_create_service_reminder_subscriptions.php');

        $this->assertStringContainsString("Schema::create('service_reminder_subscriptions'", $migration);
        $this->assertStringContainsString("Schema::create('service_reminder_deliveries'", $migration);
        $this->assertStringContainsString("\$table->unique(['subscription_id', 'service_occurrence_id']", $migration);
        $this->assertStringContainsString("\$table->foreignUuid('person_id')->constrained('people')", $migration);
    }

    public function test_reminder_actions_are_member_owned_and_email_delivery_gated(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/MemberServiceReminderController.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        $this->assertStringContainsString('if (! $delivery->active())', $controller);
        $this->assertStringContainsString("where('person_id', \$personId)", $controller);
        $this->assertStringContainsString("Route::middleware('auth')->post('/member/service-reminders'", $routes);
        $this->assertStringContainsString("Route::middleware('auth')->delete('/member/service-reminders/{subscription}'", $routes);
    }

    public function test_delivery_is_scheduled_idempotently_and_uses_iana_timezones(): void
    {
        $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/SendServiceRemindersCommand.php');
        $schedule = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/public/service-times.blade.php');

        $this->assertStringContainsString("where('subscription_id', \$subscription->id)", $command);
        $this->assertStringContainsString("where('service_occurrence_id', \$occurrence->id)", $command);
        $this->assertStringContainsString('setTimezone($memberTimezone)', $command);
        $this->assertStringContainsString("Schedule::command('services:send-reminders --limit=100')->everyFiveMinutes()", $schedule);
        $this->assertStringContainsString('data-viewer-timezone-input', $view);
        $this->assertStringContainsString('$remindersAvailable || $serviceReminders->isNotEmpty()', $view);
    }

    public function test_reminder_picker_is_viewport_aware_and_dismissible(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/public/service-times.blade.php');
        $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertStringContainsString('data-dismiss-on-outside data-viewport-popover', $view);
        $this->assertStringContainsString('initializeDismissibleSurfaces', $script);
        $this->assertStringContainsString('panel.style.left = `${left}px`', $script);
        $this->assertStringContainsString("event.key !== 'Escape'", $script);
        $this->assertStringContainsString('.service-reminder-picker>form{position:fixed', $styles);
    }
}
