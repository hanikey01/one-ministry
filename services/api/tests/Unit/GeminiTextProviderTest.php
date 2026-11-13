<?php

namespace Tests\Unit;

use App\Services\Ai\GeminiTextProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiTextProviderTest extends TestCase
{
    public function test_model_discovery_does_not_claim_unverified_schema_support(): void
    {
        Http::fake(['*' => Http::response(['models' => [['name' => 'models/test', 'supportedGenerationMethods' => ['generateContent'], 'outputTokenLimit' => 4096]]])]);
        $models = app(GeminiTextProvider::class)->discoverModels('test-key');
        $this->assertFalse($models[0]['supports_structured_output']);
        $this->assertSame(4096, $models[0]['metadata']['output_token_limit']);
    }

    public function test_it_removes_json_schema_keywords_that_gemini_does_not_support(): void
    {
        Http::fake(function (Request $request) {
            $payload = $request->data();
            $schema = $payload['generationConfig']['responseJsonSchema'];
            $this->assertSame('application/json', $payload['generationConfig']['responseMimeType']);
            $this->assertArrayNotHasKey('responseFormat', $payload['generationConfig']);

            $this->assertSame('string', $schema['properties']['title']['type']);
            $this->assertArrayNotHasKey('maxLength', $schema['properties']['title']);
            $this->assertArrayNotHasKey('pattern', $schema['properties']['dimensions']['properties']['width']);
            $this->assertSame(['compact', 'spacious'], $schema['properties']['density']['enum']);
            $this->assertFalse($schema['additionalProperties']);

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"title":"Welcome"}']]]]]], 200);
        });

        $result = app(GeminiTextProvider::class)->generateStructuredOutput(
            'test-key',
            'gemini-test',
            'System prompt',
            'User prompt',
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 200],
                    'dimensions' => ['type' => 'object', 'properties' => ['width' => ['type' => 'string', 'pattern' => '^\\d+px$']], 'additionalProperties' => false],
                    'density' => ['type' => 'string', 'enum' => ['compact', 'spacious']],
                ],
                'additionalProperties' => false,
            ],
            'test_schema',
            120,
        );

        $this->assertSame(['title' => 'Welcome'], $result['output']);
    }
}
