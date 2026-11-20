<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

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

    public function validateConnection(string $apiKey): array
    {
        $started = microtime(true);
        try {
            Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->connectTimeout(5)->timeout(15)
                ->get('/models', ['pageSize' => 1])->throw();
        } catch (RequestException $exception) {
            throw $this->requestException($exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }
        return ['latency_ms' => (int) ((microtime(true) - $started) * 1000)];
    }

    public function validateStructuredOutput(string $apiKey, string $modelId): array
    {
        $schema = AiCompositionProbe::schema();
        $result = $this->requestStructured($apiKey, $modelId, 'You verify HTML and CSS section generation for a page editor.',
            'Return one section named Welcome with a semantic root section, a heading and paragraph in HTML, responsive self-contained CSS, empty javascript, and a plain-text fallback. Give the heading and paragraph unique kebab-case data-ai-editable attributes and clear data-ai-label attributes. Use no remote resources.',
            $schema, 1600, 0);
        AiCompositionProbe::validate($result['output']);
        return $result;
    }

    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
    {
        return $this->requestStructured($apiKey, $modelId, $systemPrompt, $userPrompt, $schema, $maxOutputTokens, 0.35);
    }

    private function requestStructured(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, int $maxTokens, float $temperature): array
    {
        $started = microtime(true); $last = null; $portable = $this->structuredOutputSchema($schema);
        foreach (['json_schema', 'json_object'] as $mode) {
            try {
                $instruction = $systemPrompt;
                $config = ['responseMimeType' => 'application/json', 'maxOutputTokens' => $maxTokens, 'temperature' => $temperature];
                if ($mode === 'json_schema') $config['responseJsonSchema'] = $portable;
                else $instruction .= "\nReturn exactly one JSON object and no prose. It must match this contract: ".json_encode($portable, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $payload = Http::baseUrl(self::BASE_URL)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->connectTimeout(10)->timeout(120)
                    ->post('/models/'.rawurlencode(preg_replace('#^models/#', '', $modelId)).':generateContent', [
                        'systemInstruction' => ['parts' => [['text' => $instruction]]],
                        'contents' => [['parts' => [['text' => $userPrompt]]]], 'generationConfig' => $config,
                    ])->throw()->json();
                if (($payload['candidates'][0]['finishReason'] ?? null) === 'MAX_TOKENS') throw new AiProviderException('output_truncated');
                $text = collect($payload['candidates'][0]['content']['parts'] ?? [])->map(fn ($part): string => is_array($part) ? (string) ($part['text'] ?? '') : '')->implode('');
                $output = $this->decodeObject($text);
                return ['model' => (string) ($payload['modelVersion'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'response_mode' => $mode, 'output' => $output];
            } catch (RequestException $exception) { $last = $this->requestException($exception); }
            catch (ConnectionException $exception) { $last = AiProviderException::fromConnection($exception); }
            catch (AiProviderException $exception) { $last = $exception; }
            catch (Throwable) { throw new AiProviderException('internal_response_processing_error'); }
            if ($mode === 'json_object' || ! in_array($last->diagnosticCode, ['provider_schema_rejected', 'provider_request_rejected', 'provider_malformed_json', 'structured_output_failed'], true)) throw $last;
        }
        throw $last ?? new AiProviderException('structured_output_failed');
    }

    private function decodeObject(mixed $content): array
    {
        if (! is_string($content) || trim($content) === '') throw new AiProviderException('structured_output_failed');
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? '');
        try { $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) {
            $start = strpos($text, '{'); $end = strrpos($text, '}');
            if ($start === false || $end === false || $end <= $start) throw new AiProviderException('provider_malformed_json');
            try { $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new AiProviderException('provider_malformed_json'); }
        }
        if (! is_array($decoded) || array_is_list($decoded)) throw new AiProviderException('structured_output_failed');
        return $decoded;
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function structuredOutputSchema(array $schema): array
    {
        // Gemini's REST structured-output contract does not support string
        // constraints such as pattern or maxLength. Those are transport-level
        // hints only; the PageDocumentService remains the enforcement boundary.
        // Keep cardinality limits in Laravel. Some Gemini transports reject
        // otherwise valid nested schemas when minItems/maxItems are present.
        $supported = ['type', 'format', 'title', 'description', 'enum', 'items', 'prefixItems', 'minimum', 'maximum', 'anyOf', 'oneOf', 'properties', 'additionalProperties', 'required', '$id', '$defs', '$ref', '$anchor'];
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
