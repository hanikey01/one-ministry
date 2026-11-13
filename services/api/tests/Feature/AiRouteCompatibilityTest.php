<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\User;
use App\Services\Ai\AiModelSettingsService;
use App\Services\Ai\AiPageDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\AiCompositionProbeTest;

class AiRouteCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_primary_remains_active_with_failed_fallback_and_per_model_results(): void
    {
        $actor = $this->routeFixture();
        Http::fake(fn ($request) => str_contains($request->url(), '/models/primary:')
            ? Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(AiCompositionProbeTest::compositionFixture())]]]]]])
            : Http::response(['error' => ['message' => 'secret prompt']], 429));

        $result = app(AiModelSettingsService::class)->testPageEditorRoute($actor, null);

        $this->assertTrue($result['degraded']);
        $this->assertSame(1, $result['models']);
        $this->assertSame('provider_rate_limited (HTTP 429)', $result['failures'][0]['reason']);
        $this->assertDatabaseHas('ai_task_routes', ['task_key' => 'page_editor', 'status' => 'degraded']);
        $metadata = json_decode(DB::table('ai_model_catalog')->where('model_id', 'primary')->value('metadata'), true);
        $this->assertTrue($metadata['page_editor_compatibility']['passed']);
        $metadata = json_decode(DB::table('ai_model_catalog')->where('model_id', 'fallback')->value('metadata'), true);
        $this->assertFalse($metadata['page_editor_compatibility']['passed']);
        $this->assertSame('provider_rate_limited', $metadata['page_editor_compatibility']['failure_code']);
        Http::assertSentCount(2);
    }

    public function test_tiny_probe_cannot_activate_route(): void
    {
        $actor = $this->routeFixture();
        $candidate = ['content' => ['parts' => [['text' => '{"ready":true,"sections":[]}']]]];
        Http::fake(['*' => Http::response(['candidates' => [$candidate]])]);
        try {
            app(AiModelSettingsService::class)->testPageEditorRoute($actor, null);
            $this->fail('Invalid probe must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Compatibility check failed', $exception->getMessage());
        }
        $this->assertDatabaseHas('ai_task_routes', ['status' => 'unverified']);
        Http::assertSentCount(2);
    }

    public function test_unavailable_primary_does_not_block_scoped_fallback(): void
    {
        $actor = $this->routeFixture();
        FeatureSetting::updateOrCreate(['key' => 'ai_page_editor'], ['state' => 'enabled']);
        DB::table('ai_task_routes')->update(['status' => 'verified']);
        DB::table('ai_model_catalog')->where('model_id', 'primary')->update(['availability' => 'unavailable']);
        DB::table('ai_model_catalog')->where('model_id', 'fallback')->update(['supports_structured_output' => true]);
        $element = ['id' => 'heading', 'type' => 'heading', 'content' => ['text' => 'Welcome'], 'settings' => [], 'responsive' => []];
        $proposal = ['type' => 'heading', 'content' => [['field' => 'text', 'value' => 'Plan your visit']], 'design' => [], 'responsive' => [], 'media' => []];
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['proposal' => $proposal])]]]]]])]);
        $result = app(AiPageDraftService::class)->reviseObject([
            'title' => 'Welcome', 'instruction' => 'Improve this heading', 'kind' => 'element', 'section' => 0, 'column' => 0, 'element' => 0,
            'document' => ['version' => 2, 'sections' => [['id' => 'section', 'type' => 'section', 'settings' => [], 'responsive' => [], 'columns' => [['id' => 'column', 'settings' => [], 'responsive' => [], 'elements' => [$element]]]]]],
        ], $actor, null);
        $this->assertTrue($result['fallback_used']);
        $this->assertSame('fallback', $result['model']);
        $this->assertSame('Plan your visit', $result['proposal']['content']['text']);
        Http::assertSentCount(1);
    }

    private function routeFixture(): User
    {
        config(['app.key' => str_repeat('t', 32)]);
        $actor = User::factory()->create();
        DB::table('roles')->updateOrInsert(['key' => 'ministry_administrator'], ['name' => 'Administrator']);
        DB::table('user_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'role_key' => 'ministry_administrator', 'scope_type' => 'installation']);
        DB::table('ai_provider_connections')->insert(['provider_key' => 'gemini', 'api_key_encrypted' => Crypt::encryptString('test-key'), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['primary', 'fallback'] as $model) {
            DB::table('ai_model_catalog')->insert(['id' => (string) Str::uuid(), 'provider_key' => 'gemini', 'model_id' => $model, 'display_name' => $model, 'supports_structured_output' => false, 'input_modalities' => '["text"]', 'output_modalities' => '["text"]', 'availability' => 'available', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('ai_task_routes')->insert(['task_key' => 'page_editor', 'primary_provider' => 'gemini', 'primary_model' => 'primary', 'fallback_models' => '[{"provider":"gemini","model_id":"fallback"}]', 'status' => 'unverified', 'created_at' => now(), 'updated_at' => now()]);

        return $actor;
    }

    public function test_unavailable_primary_does_not_block_full_page_fallback(): void
    {
        $actor = $this->routeFixture();
        FeatureSetting::updateOrCreate(['key' => 'ai_page_editor'], ['state' => 'enabled']);
        DB::table('ai_task_routes')->update(['status' => 'verified']);
        DB::table('ai_model_catalog')->where('model_id', 'primary')->update(['availability' => 'unavailable']);
        DB::table('ai_model_catalog')->where('model_id', 'fallback')->update(['supports_structured_output' => true]);
        $element = ['id' => 'heading', 'type' => 'heading', 'content' => ['text' => 'Welcome'], 'settings' => [], 'responsive' => []];
        $document = ['version' => 2, 'sections' => [['id' => 'section', 'type' => 'section', 'settings' => [], 'responsive' => [], 'columns' => [['id' => 'column', 'settings' => [], 'responsive' => [], 'elements' => [$element]]]]]];
        $plan = array_fill_keys(['page_strategy', 'visual_direction', 'typography_strategy', 'spacing_rhythm', 'responsive_strategy', 'cta_strategy', 'custom_code_reason'], 'A restrained welcome page');
        $plan += ['visitor_journey' => ['Welcome'], 'sections' => [['purpose' => 'Welcome', 'element_types' => ['heading']]], 'accessibility_priorities' => ['Readable text'], 'factual_constraints' => ['No invented facts'], 'custom_code_needed' => false];
        $draft = \Tests\Unit\AiPageCompositionCompilerTest::composition();
        $payload = fn (array $output): array => ['candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]]];
        Http::fakeSequence()->push($payload($plan))->push($payload($draft));

        $result = app(AiPageDraftService::class)->generate(['brief' => 'Create a welcome page', 'title' => 'Welcome', 'public_details' => null], $actor, null);

        $this->assertTrue($result['fallback_used']);
        $this->assertSame('fallback', $result['model']);
        $this->assertSame('Welcome', $result['document']['sections'][0]['columns'][0]['elements'][0]['content']['text']);
        $this->assertDatabaseHas('audit_events', ['action' => 'content.page_ai_draft_failed']);
        $this->assertDatabaseCount('pages', 0);
        Http::assertSentCount(2);
    }
}
