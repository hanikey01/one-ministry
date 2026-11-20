<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\User;
use App\Services\Ai\AiPageGenerationService;
use App\Services\Ai\AiPublicPageContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiPageGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        config(['app.key' => str_repeat('t', 32)]);
        $actor = User::factory()->create();
        DB::table('roles')->updateOrInsert(['key' => 'ministry_administrator'], ['name' => 'Administrator']);
        DB::table('user_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'role_key' => 'ministry_administrator', 'scope_type' => 'installation']);
        FeatureSetting::updateOrCreate(['key' => 'ai_page_editor'], ['state' => 'enabled']);

        return $actor;
    }

    public function test_durable_pipeline_returns_a_reviewable_proposal_without_saving(): void
    {
        $actor = $this->actor();
        $this->provider();
        $service = app(AiPageGenerationService::class);
        $row = $service->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $actor, 'request-one');
        $this->assertSame($row->id, $service->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $actor, 'request-one')->id);
        $this->assertStringNotContainsString('welcoming', $row->payload_encrypted);
        $this->assertTrue($service->processNext());
        $this->assertSame('composing', $service->owned($row->id, $actor)->state);
        $this->assertTrue($service->processNext());
        $this->assertSame('reviewing', $service->owned($row->id, $actor)->state);
        $this->assertTrue($service->processNext());
        $this->assertSame('compiling', $service->owned($row->id, $actor)->state);
        $this->assertTrue($service->processNext());
        $view = $service->view($service->owned($row->id, $actor));
        $this->assertSame('completed', $view['state']);
        $this->assertSame(2, $view['proposal']['document']['version']);
        $this->assertSame('Welcome', $view['proposal']['title']);
        $this->assertDatabaseCount('pages', 0);
        Http::assertSentCount(3);
    }

    public function test_cancellation_during_provider_request_cannot_be_overwritten(): void
    {
        $actor = $this->actor();
        $this->provider();
        $service = app(AiPageGenerationService::class);
        $row = $service->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $actor, 'cancel-request');
        Http::fake(function () use ($service, $row, $actor) {
            $service->cancel($row->id, $actor);

            return Http::response(['error' => ['message' => 'rate limited']], 429);
        });
        $service->processNext();
        $this->assertSame('cancelled', $service->owned($row->id, $actor)->state);
        $this->assertFalse($service->processNext());
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_stale_job_recovery_is_bounded(): void
    {
        $actor = $this->actor();
        $service = app(AiPageGenerationService::class);
        $row = $service->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $actor, 'stale-request');
        DB::table('ai_page_generations')->where('id', $row->id)->update(['state' => 'planning', 'stage_attempt' => 5, 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->subMinute()]);
        $service->processNext();
        $this->assertDatabaseHas('ai_page_generations', ['id' => $row->id, 'state' => 'failed', 'failure_code' => 'stale_attempts_exhausted']);
    }

    public function test_other_staff_cannot_poll_or_cancel_someone_elses_generation(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $row = app(AiPageGenerationService::class)->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $owner, 'private-request');
        $this->actingAs($other)->getJson(route('staff.pages.ai-generation.show', $row->id))->assertNotFound();
        $this->actingAs($other)->postJson(route('staff.pages.ai-generation.process', $row->id))->assertNotFound();
        $this->actingAs($other)->deleteJson(route('staff.pages.ai-generation.cancel', $row->id))->assertNotFound();
    }

    public function test_owner_can_advance_their_generation_without_waiting_for_scheduler(): void
    {
        $actor = $this->actor();
        $this->provider();
        $row = app(AiPageGenerationService::class)->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $actor, 'browser-driven-request');

        $this->actingAs($actor)->postJson(route('staff.pages.ai-generation.process', $row->id))
            ->assertOk()
            ->assertJsonPath('data.state', 'composing')
            ->assertJsonStructure(['data' => ['process_url', 'retry_after_ms']]);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_input(): void
    {
        $actor = $this->actor();
        $service = app(AiPageGenerationService::class);
        $service->enqueue(['brief' => 'Original ministry homepage'], $actor, 'same-key');
        $this->expectException(ValidationException::class);
        $service->enqueue(['brief' => 'Another ministry homepage'], $actor, 'same-key');
    }

    public function test_approved_media_context_excludes_unpublished_and_nonpublic_libraries(): void
    {
        $actor = $this->actor();
        foreach ([['public', 'published', 'media_library'], ['draft', 'draft', 'media_library'], ['private', 'published', 'books_library']] as [$title, $status, $library]) {
            DB::table('media_assets')->insert(['id' => (string) Str::uuid(), 'title' => $title, 'status' => $status, 'library' => $library, 'kind' => 'image', 'provider' => 'local', 'provider_key' => $title.'.jpg', 'file_path' => $title.'.jpg', 'file_mime' => 'image/jpeg']);
        }
        $context = app(AiPublicPageContext::class);
        $snapshot = $context->snapshot($actor);
        $this->assertCount(1, $snapshot['media']);
        $this->assertSame('public', array_values($snapshot['media'])[0]['title']);
        $this->assertArrayNotHasKey('url', $context->forPrompt($snapshot)['media'][0]);
        $this->assertSame('/media/'.array_values($snapshot['media'])[0]['id'], $context->forDesignPrompt($snapshot)['media'][0]['path']);
        $this->assertSame([$context->forDesignPrompt($snapshot)['media'][0]['path']], $context->approvedMediaPaths($snapshot));
    }

    public function test_composition_retry_retains_the_successful_plan(): void
    {
        $actor = $this->actor();
        $this->provider(true);
        $service = app(AiPageGenerationService::class);
        $row = $service->enqueue(['brief' => 'Design a welcoming public ministry homepage'], $actor, 'retry-composition');
        $service->processNext();
        $service->processNext();
        $this->assertSame('composing', $service->owned($row->id, $actor)->state);
        $this->travel(16)->seconds();
        $service->processNext();
        $service->processNext();
        $service->processNext();
        $view = $service->view($service->owned($row->id, $actor));
        $this->assertSame('completed', $view['state']);
        $this->assertSame('A clear welcome', $view['proposal']['design_plan']['page_strategy']);
        Http::assertSentCount(4);
    }

    public function test_generation_is_rejected_when_feature_is_disabled(): void
    {
        $actor = $this->actor();
        FeatureSetting::where('key', 'ai_page_editor')->update(['state' => 'disabled']);
        $this->actingAs($actor)->postJson(route('staff.pages.ai-draft'), ['brief' => 'Design a welcoming public ministry homepage'])->assertNotFound();
        $this->assertDatabaseCount('ai_page_generations', 0);
    }

    public function test_design_review_approval_keeps_the_valid_composition(): void
    {
        $actor = $this->actor();
        $this->provider();
        $service = app(AiPageGenerationService::class);
        $row = $service->enqueue(['brief' => 'Design a welcoming public ministry homepage', 'review' => true], $actor, 'optional-review');
        $service->processNext();
        $service->processNext();
        $service->processNext();
        $service->processNext();
        $view = $service->view($service->owned($row->id, $actor));
        $this->assertSame('completed', $view['state']);
        $this->assertSame('approved', $view['proposal']['review_status']);
        $this->assertStringContainsString('Welcome', $view['proposal']['document']['sections'][0]['columns'][0]['elements'][0]['content']['html']);
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_http_generation_returns_a_queued_record_and_never_calls_provider_inline(): void
    {
        $actor = $this->actor();
        Http::fake();
        $this->actingAs($actor)->postJson(route('staff.pages.ai-draft'), ['brief' => 'Design a welcoming public ministry homepage'], ['Idempotency-Key' => 'http-request'])
            ->assertStatus(202)->assertJsonPath('data.state', 'queued');
        $this->assertDatabaseCount('ai_page_generations', 1);
        $this->assertDatabaseCount('pages', 0);
        Http::assertNothingSent();
    }

    private function provider(bool $retryComposition = false): void
    {
        DB::table('ai_provider_connections')->insert(['provider_key' => 'gemini', 'api_key_encrypted' => Crypt::encryptString('test-key'), 'enabled' => true]);
        DB::table('ai_model_catalog')->insert(['id' => (string) Str::uuid(), 'provider_key' => 'gemini', 'model_id' => 'primary', 'display_name' => 'Primary', 'supports_structured_output' => true, 'input_modalities' => '["text"]', 'output_modalities' => '["text"]', 'availability' => 'available']);
        DB::table('ai_task_routes')->insert(['task_key' => 'page_editor', 'primary_provider' => 'gemini', 'primary_model' => 'primary', 'fallback_models' => '[]', 'status' => 'verified']);
        $plan = array_fill_keys(['page_strategy', 'visual_direction', 'typography_strategy', 'spacing_rhythm', 'responsive_strategy', 'cta_strategy', 'art_direction', 'color_strategy', 'imagery_strategy', 'motion_strategy', 'signature_motif', 'section_transitions'], 'A clear welcome');
        $plan += ['visitor_journey' => ['Welcome'], 'sections' => [['name' => 'Welcome hero', 'purpose' => 'Welcome', 'hierarchy' => 'One heading', 'layout' => 'Editorial', 'responsive' => 'Stack on mobile', 'cta' => 'Plan a visit']], 'anti_patterns' => ['No generic cards'], 'accessibility_priorities' => ['Readability'], 'factual_constraints' => ['No inventions']];
        $payload = fn ($output) => ['candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]]];
        $sequence = Http::fakeSequence()->push($payload($plan));
        if ($retryComposition) {
            $sequence->push(['error' => ['message' => 'Unavailable']], 503);
        }
        $sequence->push($payload(['section' => ['name'=>'Welcome hero','purpose'=>'Welcome visitors','html'=>'<section class="hero"><h1 data-ai-editable="hero-heading" data-ai-label="Hero heading">Welcome</h1><p data-ai-editable="hero-introduction" data-ai-label="Hero introduction">Plan your visit.</p></section>','css'=>'.hero{padding:3rem;background:var(--surface);color:var(--ink)}','javascript'=>'','fallback'=>'Welcome. Plan your visit.']]));
        $sequence->push($payload(['approved'=>true,'overall_score'=>88,'coherence_score'=>90,'originality_score'=>84,'hierarchy_score'=>90,'craft_score'=>89,'issues'=>[],'repair_section'=>0,'repair_instructions'=>'']));
    }
}
