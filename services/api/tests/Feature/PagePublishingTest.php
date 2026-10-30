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

    public function test_editor_publish_saves_and_publishes_the_submitted_document_atomically(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($admin);

        $id = $this->postJson('/api/v1/staff/pages', ['title' => 'About us', 'slug' => 'about-us', 'body' => 'Old copy.'])
            ->assertCreated()->json('data.id');
        $document = [
            'version' => 2,
            'sections' => [[
                'id' => 'section-main', 'type' => 'section', 'settings' => [
                    'title' => 'Welcome section', 'body' => 'Section introduction', 'content_width' => 'custom',
                    'content_max_width' => '1180px', 'min_height' => '70vh', 'vertical_alignment' => 'center',
                    'row_gap' => '2rem', 'grid_preset' => 'one', 'background_image' => '/media/example-image',
                    'background_color' => '#112233', 'overlay_opacity' => '35', 'border_style' => 'solid',
                    'border_width' => '1px', 'custom_id' => 'published-section', 'custom_class' => 'published-layout',
                    'custom_css' => 'selector { outline: 1px solid #112233; }',
                ],
                'responsive' => [
                    'desktop' => ['alignment' => 'stretch', 'padding' => ['top' => '40px', 'right' => '2rem', 'bottom' => '40px', 'left' => '2rem']],
                    'mobile' => ['visibility' => 'visible', 'column_count' => '1', 'stack' => 'stack'],
                ],
                'columns' => [[
                    'id' => 'column-main', 'settings' => ['custom_id' => 'published-column', 'min_height' => '320px', 'vertical_alignment' => 'end', 'element_gap' => '1.5rem'],
                    'responsive' => ['desktop' => ['width' => 'custom', 'custom_width' => '72%', 'padding' => ['top' => '8px', 'right' => '12px', 'bottom' => '8px', 'left' => '12px']]],
                    'elements' => [[
                        'id' => 'heading-main', 'type' => 'heading', 'content' => ['text' => 'New published copy'],
                        'settings' => ['font_family' => 'georgia', 'font_size' => '32px', 'font_weight' => '700', 'text_color' => '#ffffff', 'button_width' => 'full'],
                        'responsive' => ['tablet' => ['font_size' => '28px', 'alignment' => 'center', 'margin' => ['top' => '4px', 'right' => '0px', 'bottom' => '4px', 'left' => '0px']]],
                    ]],
                ]],
            ]],
        ];

        $response = $this->putJson("/staff/pages/{$id}", [
            'title' => 'About us', 'slug' => 'about-us', 'blocks' => json_encode($document), 'publish' => '1',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.document.sections.0.settings.content_width', 'custom')
            ->assertJsonPath('data.document.sections.0.settings.background_image', '/media/example-image')
            ->assertJsonPath('data.document.sections.0.responsive.mobile.stack', 'stack')
            ->assertJsonPath('data.document.sections.0.columns.0.responsive.desktop.custom_width', '72%')
            ->assertJsonPath('data.document.sections.0.columns.0.elements.0.responsive.tablet.font_size', '28px');

        $record = DB::table('pages')->where('id', $id)->first();
        $stored = json_decode($record->blocks, true);
        $this->assertSame('published', $record->status);
        $this->assertSame($response->json('data.document'), $stored);
        $this->assertSame('New published copy', $stored['sections'][0]['columns'][0]['elements'][0]['content']['text']);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_published', 'subject_id' => $id]);
        $this->get('/about-us')->assertOk()
            ->assertSee('id="published-section"', false)
            ->assertSee(':where(#published-section) { outline: 1px solid #112233; }', false)
            ->assertSee('--page-column-padding: 8px 12px 8px 12px', false);
    }

    public function test_publishing_accepts_a_standalone_button_without_a_destination(): void
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

        $id = $this->postJson('/api/v1/staff/pages', ['title' => 'About us', 'slug' => 'about-us', 'body' => 'Welcome.'])
            ->assertCreated()->json('data.id');
        DB::table('pages')->where('id', $id)->update(['blocks' => json_encode([
            'version' => 2,
            'sections' => [[
                'id' => 'section-main', 'type' => 'section', 'settings' => [], 'responsive' => [],
                'columns' => [[
                    'id' => 'column-main', 'settings' => [], 'responsive' => [],
                    'elements' => [['id' => 'button-main', 'type' => 'button', 'content' => ['label' => 'Learn more', 'url' => ''], 'settings' => [], 'responsive' => []]],
                ]],
            ]],
        ])]);

        $this->post("/staff/pages/{$id}/publish")->assertRedirect("/staff/pages/{$id}/edit");
        $this->assertDatabaseHas('pages', ['id' => $id, 'status' => 'published']);
    }

    public function test_every_page_starter_opens_as_a_native_structured_document(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($admin);

        foreach (['homepage', 'about', 'visit', 'beliefs', 'leadership', 'contact', 'blank'] as $template) {
            $this->get("/staff/pages/create?template={$template}")
                ->assertOk()
                ->assertViewHas('blocks', function (array $document): bool {
                    if (($document['version'] ?? null) !== 2 || empty($document['sections'])) {
                        return false;
                    }

                    $encoded = json_encode($document, JSON_THROW_ON_ERROR);

                    return ! str_contains($encoded, '"data"')
                        && ! str_contains($encoded, '/pages/');
                });
        }
    }

    public function test_a_new_page_can_be_created_as_a_draft_or_published_directly(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($admin);
        $document = json_encode([
            'version' => 2,
            'sections' => [[
                'id' => 'section-main', 'type' => 'section', 'settings' => [], 'responsive' => [],
                'columns' => [['id' => 'column-main', 'settings' => [], 'responsive' => [], 'elements' => []]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->post('/staff/pages', ['title' => 'Draft page', 'slug' => 'draft-page', 'blocks' => $document])
            ->assertRedirect();
        $this->assertDatabaseHas('pages', ['slug' => 'draft-page', 'status' => 'draft']);

        $this->post('/staff/pages', ['title' => 'Published page', 'slug' => 'published-page', 'blocks' => $document, 'publish' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('pages', ['slug' => 'published-page', 'status' => 'published']);
    }
}
