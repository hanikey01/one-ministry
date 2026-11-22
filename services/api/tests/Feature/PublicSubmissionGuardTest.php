<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Services\PublicSubmissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_legitimate_contact_is_accepted_and_duplicate_is_suppressed(): void
    {
        $this->install();
        $token = app(PublicSubmissionGuard::class)->token('public.contact.submit');
        $this->travel(3)->seconds();
        $payload = [
            '_form_started_at' => $token, 'website' => '', 'first_name' => 'Grace', 'last_name' => 'Guest',
            'email' => 'grace@example.org', 'message' => 'Please contact me.', 'consent_to_contact' => '1',
        ];

        $this->post('/contact', $payload)->assertRedirect();
        $this->post('/contact', $payload)->assertSessionHasErrors('form');
        $this->assertSame(1, DB::table('people')->where('email', 'grace@example.org')->count());
    }

    public function test_honeypot_and_too_fast_submissions_are_blocked(): void
    {
        $this->install();
        $token = app(PublicSubmissionGuard::class)->token('public.contact.submit');
        $base = ['_form_started_at' => $token, 'first_name' => 'Bot', 'consent_to_contact' => '1'];

        $this->post('/contact', $base + ['website' => 'https://spam.example'])->assertSessionHasErrors('form');
        $this->post('/contact', $base + ['website' => ''])->assertSessionHasErrors('form');
        $this->assertDatabaseCount('people', 0);
    }

    public function test_every_required_public_form_category_uses_the_guard(): void
    {
        foreach ([
            'register.store', 'register.verify.store', 'register.resend', 'password.email',
            'public.contact.submit', 'member.prayer', 'member.testimonies.store',
            'public.events.register', 'member.next-steps.request',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, $routeName);
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with($middleware, 'public_form_guard')), $routeName);
        }
    }

    private function install(): void
    {
        app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'UTC', 'location' => 'Main',
            'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
    }
}
