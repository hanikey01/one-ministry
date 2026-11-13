<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiTextProvider implements AiTextProvider
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function discoverModels(string $apiKey): array
    {
        try {
            $response = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->connectTimeout(5)->timeout(20)->retry(2, 250)->get('/models', ['pageSize' => 1000])->throw()->json('models', []);
        } catch (RequestException $exception) {
            throw $this->catalogException($exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }
        if (! is_array($response)) throw new RuntimeException('invalid_catalog');

        return collect($response)->filter(fn ($item): bool => is_array($item)
            && is_string($item['name'] ?? null)
            && in_array('generateContent', $item['supportedGenerationMethods'] ?? [], true))
            ->map(fn (array $item): array => [
                'model_id' => preg_replace('#^models/#', '', $item['name']),
                'display_name' => (string) ($item['displayName'] ?? $item['name']),
                'context_window' => isset($item['inputTokenLimit']) ? (int) $item['inputTokenLimit'] : null,
                // Listing generateContent support is not evidence of schema support.
                'supports_structured_output' => false,
                'input_modalities' => ['text'],
                'output_modalities' => ['text'],
                'metadata' => ['output_token_limit' => isset($item['outputTokenLimit']) ? (int) $item['outputTokenLimit'] : null],
            ])->values()->all();
    }

    private function catalogException(RequestException $exception): RuntimeException
    {
        return AiProviderException::fromHttp($exception);
    }

    public function validateStructuredOutput(string $apiKey, string $modelId): array
    {
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']];
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->timeout(20)->post('/models/'.rawurlencode(preg_replace('#^models/#', '', $modelId)).':generateContent', [
                'contents' => [['parts' => [['text' => 'Return a JSON object with ok set to true.']]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $schema, 'maxOutputTokens' => 128, 'temperature' => 0],
            ])->throw()->json();
        } catch (RequestException $exception) {
            throw $this->requestException($exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }
        $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $output = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($output) || ($output['ok'] ?? null) !== true) throw new AiProviderException('structured_output_failed');

        return ['model' => (string) ($payload['modelVersion'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => ['ok' => true]];
    }

    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
    {
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->connectTimeout(10)->timeout(25)->post('/models/'.rawurlencode(preg_replace('#^models/#', '', $modelId)).':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['parts' => [['text' => $userPrompt]]]],
                // Gemini accepts only a subset of JSON Schema. Laravel still
                // performs the complete document validation after generation.
                'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $this->structuredOutputSchema($schema), 'maxOutputTokens' => $maxOutputTokens, 'temperature' => 0.35],
            ])->throw()->json();
        } catch (RequestException $exception) {
            throw $this->requestException($exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }
        if (($payload['candidates'][0]['finishReason'] ?? null) === 'MAX_TOKENS') {
            throw new AiProviderException('output_truncated');
        }
        $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $output = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($output)) throw new AiProviderException('structured_output_failed');

        return ['model' => (string) ($payload['modelVersion'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => $output];
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function structuredOutputSchema(array $schema): array
    {
        // Gemini's REST structured-output contract does not support string
        // constraints such as pattern or maxLength. Those are transport-level
        // hints only; the PageDocumentService remains the enforcement boundary.
        $supported = ['type', 'format', 'title', 'description', 'enum', 'items', 'prefixItems', 'minItems', 'maxItems', 'minimum', 'maximum', 'anyOf', 'oneOf', 'properties', 'additionalProperties', 'required', '$id', '$defs', '$ref', '$anchor'];
        $clean = [];
        foreach ($schema as $key => $value) {
            if (! in_array($key, $supported, true)) continue;
            if (in_array($key, ['properties', '$defs'], true) && is_array($value)) {
                $clean[$key] = array_map(fn (mixed $child): mixed => is_array($child) ? $this->structuredOutputSchema($child) : $child, $value);
            } elseif (in_array($key, ['items'], true) && is_array($value)) {
                $clean[$key] = $this->structuredOutputSchema($value);
            } elseif (in_array($key, ['anyOf', 'oneOf', 'prefixItems'], true) && is_array($value)) {
                $clean[$key] = array_map(fn (mixed $child): mixed => is_array($child) ? $this->structuredOutputSchema($child) : $child, $value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function requestException(RequestException $exception): RuntimeException
    {
        return AiProviderException::fromHttp($exception);
    }
}
