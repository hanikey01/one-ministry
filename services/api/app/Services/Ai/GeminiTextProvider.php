<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiTextProvider implements AiTextProvider
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function discoverModels(string $apiKey): array
    {
        $response = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->timeout(15)->get('/models')->throw()->json('models', []);
        if (! is_array($response)) throw new RuntimeException('invalid_catalog');

        return collect($response)->filter(fn ($item): bool => is_array($item)
            && is_string($item['name'] ?? null)
            && in_array('generateContent', $item['supportedGenerationMethods'] ?? [], true))
            ->map(fn (array $item): array => [
                'model_id' => preg_replace('#^models/#', '', $item['name']),
                'display_name' => (string) ($item['displayName'] ?? $item['name']),
                'context_window' => isset($item['inputTokenLimit']) ? (int) $item['inputTokenLimit'] : null,
                'supports_structured_output' => true,
                'input_modalities' => ['text'],
                'output_modalities' => ['text'],
                'metadata' => ['output_token_limit' => isset($item['outputTokenLimit']) ? (int) $item['outputTokenLimit'] : null],
            ])->values()->all();
    }

    public function validateStructuredOutput(string $apiKey, string $modelId): array
    {
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']];
        $started = microtime(true);
        try {
            $payload = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->timeout(20)->post('/models/'.rawurlencode(preg_replace('#^models/#', '', $modelId)).':generateContent', [
                'contents' => [['parts' => [['text' => 'Return a JSON object with ok set to true.']]]],
                'generationConfig' => ['responseFormat' => ['text' => ['mimeType' => 'APPLICATION_JSON', 'schema' => $schema]], 'maxOutputTokens' => 24, 'temperature' => 0],
            ])->throw()->json();
        } catch (RequestException $exception) {
            $message = (string) ($exception->response?->json('error.message') ?? 'request rejected');
            $message = substr((string) preg_replace('/[^a-zA-Z0-9 .:_-]/', '', $message), 0, 180);
            throw new RuntimeException('provider_http_'.($exception->response?->status() ?? 'unknown').': '.$message, previous: $exception);
        }
        $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $output = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($output) || ($output['ok'] ?? null) !== true) throw new RuntimeException('structured_output_failed');

        return ['model' => (string) ($payload['modelVersion'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => ['ok' => true]];
    }

    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
    {
        $started = microtime(true);
        $payload = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->timeout(45)->post('/models/'.rawurlencode(preg_replace('#^models/#', '', $modelId)).':generateContent', [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['parts' => [['text' => $userPrompt]]]],
            'generationConfig' => ['responseFormat' => ['text' => ['mimeType' => 'APPLICATION_JSON', 'schema' => $schema]], 'maxOutputTokens' => $maxOutputTokens, 'temperature' => 0.35],
        ])->throw()->json();
        $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $output = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($output)) throw new RuntimeException('structured_output_failed');

        return ['model' => (string) ($payload['modelVersion'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'output' => $output];
    }
}
