<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\OpenRouterTextProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterTextProviderTest extends TestCase
{
    public function test_response_format_alone_is_not_schema_capability_evidence(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            ['id' => 'json-mode', 'supported_parameters' => ['response_format']],
            ['id' => 'schema-mode', 'supported_parameters' => ['structured_outputs']],
        ]])]);
        $models = app(OpenRouterTextProvider::class)->discoverModels('test-key');
        $this->assertFalse($models[0]['supports_structured_output']);
        $this->assertTrue($models[1]['supports_structured_output']);
    }

    public function test_structured_generation_requires_an_upstream_provider_that_honours_the_request_parameters(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'google/gemini-test',
                'choices' => [['message' => ['content' => '{"ready":true}']]],
            ]),
        ]);

        $result = app(OpenRouterTextProvider::class)->generateStructuredOutput(
            'private-key', 'google/gemini-test', 'Return JSON.', 'Check readiness.',
            ['type' => 'object', 'properties' => ['ready' => ['type' => 'boolean']], 'required' => ['ready']],
            'page_editor_probe', 180,
        );

        $this->assertTrue($result['output']['ready']);
        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && ($body['provider']['require_parameters'] ?? false) === true
                && ($body['response_format']['type'] ?? null) === 'json_schema'
                && ($body['response_format']['json_schema']['strict'] ?? false) === true;
        });
    }

    public function test_compatibility_probe_requires_strict_structured_output_support(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'google/gemini-test',
                'choices' => [['message' => ['content' => json_encode(AiCompositionProbeTest::compositionFixture())]]],
            ]),
        ]);

        $result = app(OpenRouterTextProvider::class)->validateStructuredOutput('private-key', 'google/gemini-test');

        $this->assertSame('Welcome', $result['output']['section']['name']);
        Http::assertSent(fn ($request): bool => ($request->data()['provider']['require_parameters'] ?? false) === true
            && ($request->data()['response_format']['type'] ?? null) === 'json_schema'
            && ($request->data()['response_format']['json_schema']['strict'] ?? false) === true
            && isset($request->data()['response_format']['json_schema']['schema']['properties']['section']['properties']['html']));
    }

    public function test_structured_generation_removes_keywords_rejected_by_upstream_models(): void
    {
        Http::fake(function ($request) {
            $schema = $request->data()['response_format']['json_schema']['schema'];

            $this->assertSame('string', $schema['properties']['title']['type']);
            $this->assertArrayNotHasKey('maxLength', $schema['properties']['title']);
            $this->assertArrayNotHasKey('pattern', $schema['properties']['dimensions']['properties']['width']);
            $this->assertFalse($schema['additionalProperties']);

            return Http::response([
                'model' => 'google/gemini-test',
                'choices' => [['message' => ['content' => '{"title":"Welcome"}']]],
            ]);
        });

        $result = app(OpenRouterTextProvider::class)->generateStructuredOutput(
            'private-key',
            'google/gemini-test',
            'Return JSON.',
            'Design the page.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 200],
                    'dimensions' => ['type' => 'object', 'properties' => ['width' => ['type' => 'string', 'pattern' => '^\\d+px$']], 'additionalProperties' => false],
                ],
                'required' => ['title'],
                'additionalProperties' => false,
            ],
            'page_editor_draft',
            8000,
        );

        $this->assertSame('Welcome', $result['output']['title']);
    }

    public function test_compatibility_probe_reports_malformed_provider_json(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{broken']]]])]);

        try {
            app(OpenRouterTextProvider::class)->validateStructuredOutput('private-key', 'google/gemini-test');
            $this->fail('Malformed provider JSON must fail.');
        } catch (AiProviderException $exception) {
            $this->assertSame('provider_malformed_json', $exception->diagnosticCode);
        }
    }

    public function test_schema_rejection_falls_back_to_json_object_without_changing_model(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'JSON schema is not supported']], 400)
            ->push(['model' => 'google/gemini-test', 'choices' => [['message' => ['content' => [
                ['type' => 'output_text', 'text' => "```json\n".json_encode(AiCompositionProbeTest::compositionFixture())."\n```"],
            ]]]]], 200);

        $result = app(OpenRouterTextProvider::class)->validateStructuredOutput('private-key', 'google/gemini-test');

        $this->assertSame('json_object', $result['response_mode']);
        $this->assertSame('google/gemini-test', $result['model']);
        Http::assertSent(fn ($request): bool => ($request->data()['response_format']['type'] ?? null) === 'json_object'
            && ($request->data()['provider']['allow_fallbacks'] ?? true) === false);
    }
}
