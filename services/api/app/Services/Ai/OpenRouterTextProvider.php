<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use Illuminate\Http\Client\ConnectionException;
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
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
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
                    'supports_structured_output' => in_array('structured_outputs', $parameters, true),
                    'input_modalities' => array_values(array_map(fn ($modality): string => strtolower((string) $modality), $inputs)),
                    'output_modalities' => array_values(array_map(fn ($modality): string => strtolower((string) $modality), $outputs)),
                    'metadata' => ['pricing' => $item['pricing'] ?? null, 'canonical_slug' => $item['canonical_slug'] ?? null],
                ];
            })->values()->all();
    }

    private function catalogException(RequestException $exception): RuntimeException
    {
        return AiProviderException::fromHttp($exception);
    }

    public function validateStructuredOutput(string $apiKey, string $modelId): array
    {
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->connectTimeout(10)->timeout(45)->post('/chat/completions', [
                'model' => $modelId,
                'messages' => [['role' => 'user', 'content' => 'Return a JSON object with ok set to true.']],
                'max_tokens' => 128,
                'temperature' => 0,
                'provider' => ['require_parameters' => true],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'ai_connection_check', 'strict' => true, 'schema' => $schema]],
            ])->throw()->json();
        } catch (RequestException $exception) {
            throw $this->catalogException($exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }
        $content = $payload['choices'][0]['message']['content'] ?? null;
        $output = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($output) || ($output['ok'] ?? null) !== true) throw new AiProviderException('structured_output_failed');

        return ['model' => (string) ($payload['model'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => ['ok' => true]];
    }

    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
    {
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->connectTimeout(10)->timeout(120)->post('/chat/completions', [
                'model' => $modelId,
                'messages' => [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $userPrompt]],
                'max_tokens' => $maxOutputTokens,
                'temperature' => 0.35,
                // Do not allow OpenRouter to silently select an upstream provider
                // that ignores this request's structured-output contract.
                'provider' => ['require_parameters' => true],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $this->structuredOutputSchema($schema)]],
            ])->throw()->json();
        } catch (RequestException $exception) {
            throw $this->catalogException($exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }
        if (($payload['choices'][0]['finish_reason'] ?? null) === 'length') {
            throw new AiProviderException('output_truncated');
        }
        $content = $payload['choices'][0]['message']['content'] ?? null;
        $output = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($output)) throw new AiProviderException('structured_output_failed');

        return ['model' => (string) ($payload['model'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => $output];
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function structuredOutputSchema(array $schema): array
    {
        // OpenRouter forwards the schema to the selected upstream provider.
        // Models such as Gemini reject otherwise-valid JSON Schema keywords
        // including pattern and maxLength. Laravel performs the complete,
        // authoritative document validation after generation, so the provider
        // receives only the portable structural subset.
        $supported = ['type', 'format', 'title', 'description', 'enum', 'items', 'prefixItems', 'minItems', 'maxItems', 'minimum', 'maximum', 'anyOf', 'oneOf', 'properties', 'additionalProperties', 'required', '$id', '$defs', '$ref', '$anchor'];
        $clean = [];
        foreach ($schema as $key => $value) {
            if (! in_array($key, $supported, true)) continue;
            if (in_array($key, ['properties', '$defs'], true) && is_array($value)) {
                $clean[$key] = array_map(fn (mixed $child): mixed => is_array($child) ? $this->structuredOutputSchema($child) : $child, $value);
            } elseif ($key === 'items' && is_array($value)) {
                $clean[$key] = $this->structuredOutputSchema($value);
            } elseif (in_array($key, ['anyOf', 'oneOf', 'prefixItems'], true) && is_array($value)) {
                $clean[$key] = array_map(fn (mixed $child): mixed => is_array($child) ? $this->structuredOutputSchema($child) : $child, $value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

}
