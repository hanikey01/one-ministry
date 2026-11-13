<?php

namespace Tests\Unit;

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
                'choices' => [['message' => ['content' => '{"ok":true}']]],
            ]),
        ]);

        $result = app(OpenRouterTextProvider::class)->validateStructuredOutput('private-key', 'google/gemini-test');

        $this->assertTrue($result['output']['ok']);
        Http::assertSent(fn ($request): bool => ($request->data()['provider']['require_parameters'] ?? false) === true
            && ($request->data()['response_format']['type'] ?? null) === 'json_schema'
            && ($request->data()['response_format']['json_schema']['strict'] ?? false) === true);
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
}
