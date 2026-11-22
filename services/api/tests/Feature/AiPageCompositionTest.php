<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Contracts\AiTextProvider;
use App\Services\Ai\AiPageDraftService;
use App\Services\Ai\GeminiTextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiPageCompositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_builds_and_revises_independent_html_css_sections_without_saving_a_page(): void
    {
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org', 'password' => 'long-private-password',
        ]);
        DB::table('feature_settings')->where('key', 'ai_page_editor')->update(['state' => 'enabled']);
        DB::table('ai_provider_connections')->insert(['provider_key' => 'gemini', 'api_key_encrypted' => Crypt::encryptString('test-key'), 'enabled' => true]);
        DB::table('ai_model_catalog')->insert(['id' => (string) Str::uuid(), 'provider_key' => 'gemini', 'model_id' => 'gemini-test', 'display_name' => 'Gemini test', 'supports_structured_output' => true, 'input_modalities' => '["text"]', 'output_modalities' => '["text"]', 'availability' => 'available']);
        DB::table('ai_task_routes')->insert(['task_key' => 'page_editor', 'primary_provider' => 'gemini', 'primary_model' => 'gemini-test', 'fallback_models' => '[]', 'status' => 'verified']);

        $provider = new class implements AiTextProvider {
            public array $schemas = [];
            public function discoverModels(string $apiKey): array { return []; }
            public function validateConnection(string $apiKey): array { return ['latency_ms' => 1]; }
            public function validateStructuredOutput(string $apiKey, string $modelId): array { return ['model'=>$modelId,'latency_ms'=>1,'response_mode'=>'json_schema','output'=>[]]; }
            public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
            {
                $this->schemas[$schemaName] = $schema;
                if ($schemaName === 'ministry_public_page_design_plan') return ['model'=>$modelId,'latency_ms'=>1,'response_mode'=>'json_schema','output'=>[
                    'page_strategy'=>'Help visitors plan a visit','visitor_journey'=>['Welcome'],'sections'=>[['name'=>'Welcome hero','purpose'=>'Welcome first-time visitors','hierarchy'=>'One clear headline','layout'=>'Editorial split','responsive'=>'Stack on mobile','cta'=>'Plan a visit']],
                    'visual_direction'=>'Warm editorial','typography_strategy'=>'Strong heading','spacing_rhythm'=>'Generous','responsive_strategy'=>'Mobile first','cta_strategy'=>'One action','art_direction'=>'Editorial warmth with confident asymmetry','color_strategy'=>'Warm neutral field with one sharp accent','imagery_strategy'=>'Full-bleed contextual photography','motion_strategy'=>'Restrained reveal motion','signature_motif'=>'Offset rules and cropped imagery','section_transitions'=>'Alternate full-bleed and quiet editorial fields','anti_patterns'=>['No generic cards'],'accessibility_priorities'=>['Contrast'],'factual_constraints'=>['No inventions'],
                ]];
                if ($schemaName === 'ministry_public_page_section') return ['model'=>$modelId,'latency_ms'=>1,'response_mode'=>'json_schema','output'=>['section'=>[
                    'name'=>'Welcome hero','purpose'=>'Welcome first-time visitors','html'=>'<section class="hero"><h1 data-ai-editable="hero-heading" data-ai-label="Hero heading">You are welcome here</h1></section>',
                    'css'=>'.hero{display:grid}','javascript'=>'','fallback'=>'You are welcome here.',
                ]]];
                $title = $schemaName === 'public_page_canvas_revision' ? 'Everything you need for Sunday' : 'You are welcome here';
                return ['model'=>$modelId,'latency_ms'=>1,'response_mode'=>'json_schema','output'=>['section'=>[
                    'name'=>'Welcome hero','purpose'=>'Welcome first-time visitors','html'=>'<section class="hero"><p data-ai-editable="hero-eyebrow" data-ai-label="Hero eyebrow">WELCOME</p><h1 data-ai-editable="hero-heading" data-ai-label="Hero heading">'.$title.'</h1><p data-ai-editable="hero-introduction" data-ai-label="Hero introduction">Plan your visit with confidence.</p></section>',
                    'css'=>'.hero{display:grid;padding:clamp(2rem,7vw,6rem);background:var(--surface);color:var(--ink)}@media(max-width:640px){.hero{padding:2rem 1rem}}','javascript'=>'','fallback'=>$title.'. Plan your visit with confidence.',
                ]]];
            }
        };
        $this->app->instance(GeminiTextProvider::class, $provider);

        $draft = app(AiPageDraftService::class)->generate(['brief'=>'Help first-time visitors prepare for a visit.','title'=>'Plan a visit'], $admin, '127.0.0.1');
        $element = $draft['document']['sections'][0]['columns'][0]['elements'][0];
        $this->assertSame('custom_code', $element['type']);
        $this->assertStringContainsString('You are welcome here', $element['content']['html']);
        $this->assertArrayNotHasKey('element_types', $provider->schemas['ministry_public_page_design_plan']['properties']['sections']['items']['properties']);
        $this->assertArrayHasKey('html', $provider->schemas['ministry_public_page_section']['properties']['section']['properties']);
        $this->assertArrayHasKey('html', $provider->schemas['ministry_public_page_section_repair']['properties']['section']['properties']);
        $this->assertDatabaseCount('pages', 0);

        $revision = app(AiPageDraftService::class)->reviseObject(['title'=>'Plan a visit','instruction'=>'Make the invitation clearer.','page_purpose'=>'','kind'=>'section','section'=>0,'column'=>null,'element'=>null,'document'=>$draft['document']], $admin, '127.0.0.1');
        $this->assertSame($draft['document']['sections'][0]['id'], $revision['proposal']['id']);
        $this->assertStringContainsString('Everything you need for Sunday', $revision['proposal']['columns'][0]['elements'][0]['content']['html']);
    }
}
