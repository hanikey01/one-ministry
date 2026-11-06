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
        DB::table('feature_settings')->where('key', 'giving')->update(['state' => 'disabled']);
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
            'document' => ['version' => 2, 'sections' => [[
                'id' => 'visit-hero', 'type' => 'section',
                'settings' => ['background_color' => '#17324d', 'content_width' => 'wide'],
                'responsive' => ['mobile' => ['stack' => 'stack']],
                'columns' => [[
                    'id' => 'visit-main', 'settings' => [], 'responsive' => [], 'elements' => [
                        ['id' => 'visit-heading', 'type' => 'hero', 'content' => ['eyebrow' => 'WELCOME', 'title' => 'You are welcome here', 'body' => 'Find the practical information you need before your first visit.', 'button_label' => '', 'button_url' => ''], 'settings' => ['text_color' => '#ffffff'], 'responsive' => []],
                        ['id' => 'visit-stats', 'type' => 'statistics', 'content' => ['title' => 'Plan your visit', 'stat_one_value' => '', 'stat_one_label' => '', 'stat_two_value' => '', 'stat_two_label' => '', 'stat_three_value' => '', 'stat_three_label' => ''], 'settings' => ['background_color' => '#ffffff', 'radius_top_left' => '16px'], 'responsive' => []],
                    ],
                ]],
            ]]],
        ];

        $provider = new class($output) implements AiTextProvider
        {
            public string $lastPrompt = '';
            public array $lastSchema = [];
            public array $prompts = [];
            public array $schemas = [];
            public function __construct(private readonly array $output) {}
            public function discoverModels(string $apiKey): array { return []; }
            public function validateStructuredOutput(string $apiKey, string $modelId): array { return ['model' => $modelId, 'latency_ms' => 1, 'output' => ['ok' => true]]; }
            public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
            {
                $this->lastPrompt = $userPrompt;
                $this->lastSchema = $schema;
                $this->prompts[$schemaName] = $userPrompt;
                $this->schemas[$schemaName] = $schema;
                if ($schemaName === 'ministry_public_page_design_plan') {
                    return ['model' => $modelId, 'latency_ms' => 1, 'output' => [
                        'page_strategy' => 'Move visitors from reassurance to practical details and one clear next step.',
                        'visitor_journey' => ['Understand the welcome', 'Learn what to expect', 'Plan a visit'],
                        'sections' => [[
                            'purpose' => 'Welcome first-time visitors', 'element_types' => ['hero', 'statistics'],
                            'hierarchy' => 'Lead with one promise and supporting practical information.',
                            'layout' => 'Wide hero followed by a compact supporting panel.',
                            'responsive' => 'Stack content and reduce heading scale on mobile.', 'cta' => 'Plan your visit',
                        ]],
                        'visual_direction' => 'Warm editorial design with confident contrast.',
                        'typography_strategy' => 'Expressive heading with restrained body typography.',
                        'spacing_rhythm' => 'Generous section spacing and compact related content.',
                        'responsive_strategy' => 'Single-column mobile flow with touch-friendly actions.',
                        'cta_strategy' => 'Use one primary visit action.',
                        'accessibility_priorities' => ['Readable contrast', 'Logical headings'],
                        'factual_constraints' => ['Use only confirmed ministry facts'],
                        'custom_code_needed' => false, 'custom_code_reason' => '',
                    ]];
                }
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
                if ($schemaName === 'ministry_public_page_object_revision') {
                    return ['model' => $modelId, 'latency_ms' => 2, 'output' => ['proposal' => [
                        'id' => 'model-tried-to-change-the-id',
                        'type' => 'hero',
                        'content' => [
                            'eyebrow' => 'FIRST VISIT',
                            'title' => 'Everything you need for Sunday',
                            'body' => 'A clear, welcoming introduction for first-time guests.',
                            'button_label' => 'Plan your visit',
                            'button_url' => 'https://invented.example/visit',
                        ],
                        'settings' => ['background_color' => '#102a43', 'text_color' => '#ffffff', 'font_size' => '42px'],
                        'responsive' => ['mobile' => ['font_size' => '32px', 'padding' => ['top' => '24px', 'right' => '20px', 'bottom' => '24px', 'left' => '20px']]],
                    ]]];
                }
                if ($schemaName === 'ministry_public_page_design_review') {
                    return ['model' => $modelId, 'latency_ms' => 1, 'output' => [
                        'findings' => [['category'=>'spacing','severity'=>'improvement','issue'=>'Related content was too loose.','correction'=>'Tightened spacing within the supporting content group.']],
                        'document' => $this->output['document'],
                    ]];
                }
                return ['model' => $modelId, 'latency_ms' => 1, 'output' => $this->output];
            }
        };
        $this->app->instance(GeminiTextProvider::class, $provider);

        $draft = app(AiPageDraftService::class)->generate([
            'brief' => 'Help first-time visitors prepare for a visit and understand what to expect.',
            'title' => null, 'public_details' => null, 'page_goal' => 'Increase confident visit planning',
            'audience' => 'First-time visitors', 'tone' => 'Warm and confident', 'visual_direction' => 'Editorial and spacious',
            'content_density' => 'balanced', 'primary_action' => 'Plan a visit', 'required_sections' => 'Welcome and practical details',
            'forbidden_patterns' => 'No carousel', 'brand_constraints' => 'Use restrained colors',
        ], $admin, '127.0.0.1');

        $sections = $draft['document']['sections'];
        $this->assertSame(['hero', 'statistics'], array_column($sections[0]['columns'][0]['elements'], 'type'));
        $this->assertSame('#17324d', $sections[0]['settings']['background_color']);
        $this->assertSame('16px', $sections[0]['columns'][0]['elements'][1]['settings']['radius_top_left']);
        $this->assertSame('Move visitors from reassurance to practical details and one clear next step.', $draft['design_plan']['page_strategy']);
        $this->assertStringContainsString('Primary audience:', $provider->prompts['ministry_public_page_design_plan']);
        $this->assertStringContainsString('Approved architecture plan:', $provider->prompts['ministry_public_page_draft']);
        $this->assertSame('spacing', $draft['design_review'][0]['category']);
        $this->assertStringContainsString('Review and repair', $provider->prompts['ministry_public_page_design_review']);
        $draftSchema = $provider->schemas['ministry_public_page_draft'];
        $this->assertSame(['title', 'seo_title', 'seo_description', 'document'], array_keys($draftSchema['properties']));
        $this->assertArrayNotHasKey('hero_eyebrow', $draftSchema['properties']);
        $this->assertFalse($draftSchema['properties']['document']['additionalProperties']);
        $elementSchema = $draftSchema['properties']['document']['properties']['sections']['items']['properties']['columns']['items']['properties']['elements']['items'];
        $elementTypes=array_map(fn(array $variant): string=>$variant['properties']['type']['enum'][0],$elementSchema['oneOf']);
        $this->assertContains('statistics', $elementTypes);
        $this->assertNotContains('giving', $elementTypes);
        $statisticsSchema=collect($elementSchema['oneOf'])->first(fn(array $variant): bool=>$variant['properties']['type']['enum']===['statistics']);
        $this->assertArrayHasKey('background_color', $statisticsSchema['properties']['settings']['properties']);
        $this->assertSame(['none','solid','dashed','dotted'], $statisticsSchema['properties']['settings']['properties']['border_style']['enum']);
        $this->assertArrayHasKey('pattern', $statisticsSchema['properties']['settings']['properties']['font_size']);
        $this->assertArrayNotHasKey('custom_js', $statisticsSchema['properties']['settings']['properties']);
        $this->assertArrayHasKey('mobile', $statisticsSchema['properties']['responsive']['properties']);

        $revision = app(AiPageDraftService::class)->reviseObject([
            'title' => $draft['title'], 'instruction' => 'Make the hero clearer.', 'page_purpose' => null,
            'document' => $draft['document'], 'kind' => 'element', 'section' => 0, 'column' => 0, 'element' => 0,
        ], $admin, '127.0.0.1');
        $this->assertSame('visit-heading', $revision['proposal']['id']);
        $this->assertSame('FIRST VISIT', $revision['proposal']['content']['eyebrow']);
        $this->assertSame('', $revision['proposal']['content']['button_url']);
        $this->assertSame('#102a43', $revision['proposal']['settings']['background_color']);
        $this->assertSame('32px', $revision['proposal']['responsive']['mobile']['font_size']);
        $this->assertSame('24px', $revision['proposal']['responsive']['mobile']['padding']['top']);
        $this->assertSame('WELCOME', $draft['document']['sections'][0]['columns'][0]['elements'][0]['content']['eyebrow']);
        $this->assertSame(0, DB::table('pages')->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_ai_draft_generated']);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_ai_object_revision_generated']);
    }

}
