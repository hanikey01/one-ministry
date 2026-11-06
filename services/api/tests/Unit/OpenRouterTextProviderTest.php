<?php

namespace Tests\Unit;

use App\Services\Ai\OpenRouterTextProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterTextProviderTest extends TestCase
{
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
                && ($body['response_format']['type'] ?? null) === 'json_schema';
        });
    }
}
