<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PagePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_follow_draft_review_publish_and_revision_workflow(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        DB::table('mfa_credentials')->insert([
            'user_id' => $admin->id, 'secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'recovery_code_hashes' => '[]', 'enabled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->withSession(['mfa_verified_at' => now()->timestamp]);

        $this->postJson('/api/v1/staff/pages', ['title' => 'Admin', 'slug' => 'admin'])->assertUnprocessable();
        $created = $this->postJson('/api/v1/staff/pages', [
            'title' => 'About us', 'slug' => 'About Us', 'body' => "Welcome.\n\nWe serve our city.",
            'seo_title' => 'About Hope Community', 'seo_description' => 'Learn about our ministry.',
        ])->assertCreated()->assertJsonPath('data.slug', 'about-us')->assertJsonPath('data.status', 'draft');
        $id = $created->json('data.id');

        $this->getJson('/api/v1/public/pages/about-us')->assertNotFound();
        $this->postJson('/api/v1/staff/pages', ['title' => 'Duplicate', 'slug' => 'ABOUT us'])->assertUnprocessable();
        $this->patchJson("/api/v1/staff/pages/{$id}/review", ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');

        $published = $this->getJson('/api/v1/public/pages/about-us')
            ->assertOk()->assertJsonPath('data.title', 'About us')->assertJsonPath('data.body', "Welcome.\n\nWe serve our city.");
        foreach (['created_by', 'updated_by', 'published_by', 'review_notes'] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $published->json('data'));
        }

        $this->patchJson("/api/v1/staff/pages/{$id}", [
            'title' => 'Our story', 'slug' => 'about-us', 'body' => 'An updated story.',
        ])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->getJson('/api/v1/public/pages/about-us')->assertNotFound();
        $this->getJson("/api/v1/staff/pages/{$id}/revisions")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.revision', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_created', 'subject_id' => $id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_reviewed', 'subject_id' => $id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_updated', 'subject_id' => $id]);
    }

    public function test_page_management_requires_staff_authentication(): void
    {
        $this->getJson('/api/v1/staff/pages')->assertUnauthorized();
        $this->postJson('/api/v1/staff/pages', ['title' => 'About', 'slug' => 'about'])->assertUnauthorized();
    }
}
