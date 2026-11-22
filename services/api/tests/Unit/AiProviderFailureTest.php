<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\GeminiTextProvider;
use App\Services\Ai\OpenRouterTextProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiProviderFailureTest extends TestCase
{
    public static function failures(): iterable
    {
        foreach ([GeminiTextProvider::class, OpenRouterTextProvider::class] as $provider) {
            foreach ([401 => 'provider_invalid_key', 403 => 'provider_access_denied', 404 => 'provider_model_unavailable', 429 => 'provider_rate_limited', 504 => 'provider_timeout', 400 => 'provider_request_rejected', 422 => 'provider_request_rejected', 503 => 'provider_unavailable'] as $status => $code) {
                yield [$provider, $status, $code];
            }
        }
    }

    #[DataProvider('failures')]
    public function test_http_failures_preserve_status_without_echoing_provider_content(string $provider, int $status, string $code): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private-key full prompt', 'status' => 'INVALID_ARGUMENT']], $status)]);

        try {
            $this->generate($provider);
            $this->fail('Expected provider failure.');
        } catch (AiProviderException $exception) {
            $this->assertSame($code, $exception->diagnosticCode);
            $this->assertSame($status, $exception->httpStatus);
            $this->assertSame('INVALID_ARGUMENT', $exception->providerStatus);
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('private-key', $exception->getMessage());
            $this->assertStringNotContainsString('full prompt', $exception->getMessage());
        }
    }

    public static function providers(): iterable
    {
        yield [GeminiTextProvider::class];
        yield [OpenRouterTextProvider::class];
    }

    #[DataProvider('providers')]
    public function test_schema_rejection_is_distinct_from_other_bad_requests(string $provider): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Schema rejected: full prompt private-key']], 400)]);
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('provider_schema_rejected');
        $this->generate($provider);
    }

    #[DataProvider('providers')]
    public function test_timeout_has_a_distinct_safe_code(string $provider): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out private-key full prompt'));
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('provider_timeout');
        $this->generate($provider);
    }

    #[DataProvider('providers')]
    public function test_connection_failures_do_not_retain_credentials(string $provider): void
    {
        Http::fake(fn () => throw new ConnectionException('private-key full prompt'));
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('provider_connection_failed');
        $this->generate($provider);
    }

    #[DataProvider('providers')]
    public function test_malformed_json_is_rejected(string $provider): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{broken']]]]],
            'choices' => [['message' => ['content' => '{broken']]],
        ])]);
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('provider_malformed_json');
        $this->generate($provider);
    }

    #[DataProvider('providers')]
    public function test_truncated_output_is_rejected_even_when_json_is_parseable(string $provider): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{}']]]]],
            'choices' => [['finish_reason' => 'length', 'message' => ['content' => '{}']]],
        ])]);
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('output_truncated');
        $this->generate($provider);
    }

    private function generate(string $provider): array
    {
        return app($provider)->generateStructuredOutput('private-key', 'test-model', 'full prompt', 'private content', ['type' => 'object'], 'test', 1000);
    }
}
