<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Contracts\AiTextProvider;
use App\Services\Ai\AiPageDraftService;
use App\Services\Ai\GeminiTextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiPageCompositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_draft_composes_varied_approved_blocks_without_saving_a_page(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        DB::table('feature_settings')->where('key', 'ai_page_editor')->update(['state' => 'enabled']);
        DB::table('ai_provider_connections')->insert([
            'provider_key' => 'gemini', 'api_key_encrypted' => Crypt::encryptString('test-key'), 'enabled' => true,
            'connection_status' => 'connected', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_model_catalog')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'provider_key' => 'gemini', 'model_id' => 'gemini-test',
            'display_name' => 'Gemini test', 'supports_structured_output' => true,
            'input_modalities' => json_encode(['text']), 'output_modalities' => json_encode(['text']),
            'availability' => 'available', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_task_routes')->insert([
            'task_key' => 'page_editor', 'primary_provider' => 'gemini', 'primary_model' => 'gemini-test',
            'fallback_models' => json_encode([['provider' => 'gemini', 'model_id' => 'gemini-test']]),
            'status' => 'verified', 'last_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $output = [
            'title' => 'Plan a visit', 'seo_title' => 'Plan a visit | Hope Community',
            'seo_description' => 'What to expect when you visit Hope Community.',
            'hero_eyebrow' => 'WELCOME', 'hero_heading' => 'You are welcome here',
            'hero_body' => 'Find the practical information you need before your first visit.',
            'call_to_action_title' => 'We would love to meet you', 'call_to_action_body' => 'Choose a next step that works for you.',
            'call_to_action_label' => 'Get in touch',
            'sections' => [
                $this->section('story', ['heading' => 'What to expect', 'body' => 'A warm welcome and clear information.']),
                $this->section('split', ['heading' => 'Find your way', 'body' => 'Add arrival details here.', 'image_alt' => 'Entrance to the ministry building']),
                $this->section('image', ['image_hint' => 'A welcoming community gathering', 'image_alt' => 'People greeting one another']),
                $this->section('quote', ['body' => 'We felt at home from the first visit.', 'attribution' => 'A visitor']),
                $this->section('list', ['heading' => 'Helpful details', 'items' => "Parking\nAccessibility"]),
                $this->section('faq', ['heading' => 'Common questions', 'question_one' => 'What should I wear?', 'answer_one' => 'Come as you are.']),
            ],
        ];

        $provider = new class($output) implements AiTextProvider
        {
            public string $lastPrompt = '';
            public function __construct(private readonly array $output) {}
            public function discoverModels(string $apiKey): array { return []; }
            public function validateStructuredOutput(string $apiKey, string $modelId): array { return ['model' => $modelId, 'latency_ms' => 1, 'output' => ['ok' => true]]; }
            public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
            {
                $this->lastPrompt = $userPrompt;
                if ($schemaName === 'ministry_public_page_section_revision') {
                    $revision = [];
                    foreach (array_keys($schema['properties']) as $key) {
                        $revision[$key] = match ($key) {
                            'text' => 'Revised welcome copy with consistent page voice.',
                            'attribution' => 'A visitor',
                            'media_url' => 'https://unexpected.example/image.jpg',
                            'button_url' => 'https://unexpected.example/',
                            default => 'Revised '.$key,
                        };
                    }
                    return ['model' => $modelId, 'latency_ms' => 2, 'output' => $revision];
                }
                return ['model' => $modelId, 'latency_ms' => 1, 'output' => $this->output];
            }
        };
        $this->app->instance(GeminiTextProvider::class, $provider);

        $draft = app(AiPageDraftService::class)->generate([
            'brief' => 'Help first-time visitors prepare for a visit and understand what to expect.',
            'title' => null, 'public_details' => null,
        ], $admin, '127.0.0.1');

        $this->assertSame(['hero', 'section', 'media_text', 'image', 'quote', 'list', 'accordion', 'call_to_action'], array_column($draft['blocks'], 'type'));
        $this->assertSame('', $draft['blocks'][2]['data']['media_url']);
        $this->assertSame('A welcoming community gathering', $draft['blocks'][3]['data']['image_hint']);
        $this->assertSame('', $draft['blocks'][7]['data']['button_url']);

        $originalBefore = $draft['blocks'][3];
        $originalAfter = $draft['blocks'][5];
        $revision = app(AiPageDraftService::class)->reviseSection([
            'title' => $draft['title'], 'instruction' => 'Make this quote warmer and clearer for a first-time visitor.',
            'page_purpose' => 'Help first-time visitors prepare for a visit.',
            'blocks' => $draft['blocks'], 'selected_index' => 4,
        ], $admin, '127.0.0.1');
        $this->assertSame('Revised welcome copy with consistent page voice.', $revision['data']['text']);
        $this->assertSame($originalBefore, $draft['blocks'][3]);
        $this->assertSame($originalAfter, $draft['blocks'][5]);
        $this->assertStringContainsString('Full ordered page context', $provider->lastPrompt);
        $this->assertStringContainsString('Help first-time visitors prepare for a visit.', $provider->lastPrompt);
        $this->assertStringContainsString('selected', $provider->lastPrompt);
        $mediaRevision = app(AiPageDraftService::class)->reviseSection([
            'title' => $draft['title'], 'instruction' => 'Improve the copy while keeping this layout.',
            'blocks' => $draft['blocks'], 'selected_index' => 2,
        ], $admin, '127.0.0.1');
        $this->assertSame('', $mediaRevision['data']['media_url']);
        $this->assertSame('', $mediaRevision['data']['button_url']);
        $this->actingAs($admin)->postJson('/staff/pages/ai-section-revision', [
            'title' => $draft['title'], 'instruction' => 'Make this quote warmer and clearer for a first-time visitor.',
            'page_purpose' => 'Help first-time visitors prepare for a visit.',
            'selected_index' => 4, 'blocks' => json_encode($draft['blocks'], JSON_THROW_ON_ERROR),
        ])->assertOk()->assertJsonPath('data.data.text', 'Revised welcome copy with consistent page voice.');

        $this->actingAs($admin)->post('/staff/pages/preview', [
            'title' => $draft['title'], 'blocks' => json_encode($draft['blocks'], JSON_THROW_ON_ERROR),
        ])->assertOk()->assertSee('public-page-hero')->assertSee('public-page-accordion')->assertSee('public-page-quote');
        $this->assertSame(0, DB::table('pages')->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_ai_draft_generated']);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_ai_section_revision_generated']);
    }

    /** @param array<string,string> $values
     *  @return array<string,string>
     */
    private function section(string $kind, array $values): array
    {
        return array_merge([
            'kind' => $kind, 'heading' => '', 'body' => '', 'items' => '', 'image_hint' => '', 'image_alt' => '', 'attribution' => '',
            'question_one' => '', 'answer_one' => '', 'question_two' => '', 'answer_two' => '', 'question_three' => '', 'answer_three' => '',
        ], $values);
    }
}
