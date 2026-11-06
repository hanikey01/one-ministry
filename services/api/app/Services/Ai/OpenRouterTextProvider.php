<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenRouterTextProvider implements AiTextProvider
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    public function discoverModels(string $apiKey): array
    {
        try {
            $models = Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->connectTimeout(5)->timeout(25)->retry(2, 250)->get('/models')->throw()->json('data', []);
        } catch (RequestException $exception) {
            throw $this->catalogException($exception);
        }
        if (! is_array($models)) throw new RuntimeException('invalid_catalog');

        return collect($models)->filter(fn ($item): bool => is_array($item) && is_string($item['id'] ?? null) && $item['id'] !== '')
            ->map(function (array $item): array {
                $parameters = is_array($item['supported_parameters'] ?? null) ? $item['supported_parameters'] : [];
                $architecture = is_array($item['architecture'] ?? null) ? $item['architecture'] : [];
                $inputs = is_array($architecture['input_modalities'] ?? null) ? $architecture['input_modalities'] : [];
                $outputs = is_array($architecture['output_modalities'] ?? null) ? $architecture['output_modalities'] : [];

                return [
                    'model_id' => (string) $item['id'],
                    'display_name' => (string) ($item['name'] ?? $item['id']),
                    'context_window' => isset($item['context_length']) ? (int) $item['context_length'] : null,
                    'supports_structured_output' => in_array('response_format', $parameters, true) || in_array('structured_outputs', $parameters, true),
                    'input_modalities' => array_values(array_map(fn ($modality): string => strtolower((string) $modality), $inputs)),
                    'output_modalities' => array_values(array_map(fn ($modality): string => strtolower((string) $modality), $outputs)),
                    'metadata' => ['pricing' => $item['pricing'] ?? null, 'canonical_slug' => $item['canonical_slug'] ?? null],
                ];
            })->values()->all();
    }

    private function catalogException(RequestException $exception): RuntimeException
    {
        $message = (string) ($exception->response?->json('error.message') ?? 'request rejected');
        $message = substr((string) preg_replace('/[^a-zA-Z0-9 .:_-]/', '', $message), 0, 180);
        return new RuntimeException('provider_http_'.($exception->response?->status() ?? 'unknown').': '.$message, previous: $exception);
    }

    public function validateStructuredOutput(string $apiKey, string $modelId): array
    {
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->timeout(20)->post('/chat/completions', [
                'model' => $modelId,
                'messages' => [['role' => 'user', 'content' => 'Return a JSON object with ok set to true.']],
                'max_tokens' => 24,
                'temperature' => 0,
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'ai_connection_check', 'strict' => true, 'schema' => $schema]],
            ])->throw()->json();
        } catch (RequestException $exception) {
            $message = (string) ($exception->response?->json('error.message') ?? 'request rejected');
            $message = substr((string) preg_replace('/[^a-zA-Z0-9 .:_-]/', '', $message), 0, 180);
            throw new RuntimeException('provider_http_'.($exception->response?->status() ?? 'unknown').': '.$message, previous: $exception);
        }
        $content = $payload['choices'][0]['message']['content'] ?? null;
        $output = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($output) || ($output['ok'] ?? null) !== true) throw new RuntimeException('structured_output_failed');

        return ['model' => (string) ($payload['model'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => ['ok' => true]];
    }

    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
    {
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->connectTimeout(10)->timeout(90)->post('/chat/completions', [
                'model' => $modelId,
                'messages' => [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $userPrompt]],
                'max_tokens' => $maxOutputTokens,
                'temperature' => 0.35,
                // Do not allow OpenRouter to silently select an upstream provider
                // that ignores this request's structured-output contract.
                'provider' => ['require_parameters' => true],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => false, 'schema' => $schema]],
            ])->throw()->json();
        } catch (RequestException $exception) {
            throw $this->catalogException($exception);
        }
        $content = $payload['choices'][0]['message']['content'] ?? null;
        $output = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($output)) throw new RuntimeException('structured_output_failed');

        return ['model' => (string) ($payload['model'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => $output];
    }

}
