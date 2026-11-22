<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

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

    public function validateConnection(string $apiKey): array
    {
        $started = microtime(true);
        try {
            Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->connectTimeout(5)->timeout(15)
                ->get('/models')->throw();
        } catch (RequestException $exception) {
            throw $this->catalogException($exception);
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
            $schema, 'ai_page_composition_check', 8192, 0);
        AiCompositionProbe::validate($result['output']);

        return $result;
    }

    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array
    {
        return $this->requestStructured($apiKey, $modelId, $systemPrompt, $userPrompt, $schema, $schemaName, $maxOutputTokens, 0.35);
    }

    private function requestStructured(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxTokens, float $temperature): array
    {
        $started = microtime(true);
        $last = null;
        foreach (['json_schema', 'json_object'] as $mode) {
            try {
                $portable = $this->structuredOutputSchema($schema);
                $messages = [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $userPrompt]];
                $format = ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $portable]];
                if ($mode === 'json_object') {
                    $format = ['type' => 'json_object'];
                    $messages[0]['content'] .= "\nReturn exactly one JSON object and no prose. It must match this contract: ".json_encode($portable, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
                $payload = Http::baseUrl(self::BASE_URL)->withToken($apiKey)->acceptJson()->connectTimeout(10)->timeout(120)
                    ->post('/chat/completions', [
                        'model' => $modelId, 'messages' => $messages, 'max_tokens' => $maxTokens, 'temperature' => $temperature,
                        'provider' => ['require_parameters' => true, 'allow_fallbacks' => false, 'data_collection' => 'deny'],
                        'plugins' => [['id' => 'response-healing']],
                        'response_format' => $format,
                    ])->throw()->json();
                if (($payload['choices'][0]['finish_reason'] ?? null) === 'length') throw new AiProviderException('output_truncated');
                $output = $this->decodeObject($payload['choices'][0]['message']['content'] ?? null);

                return ['model' => (string) ($payload['model'] ?? $modelId), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'response_mode' => $mode, 'output' => $output];
            } catch (RequestException $exception) {
                $last = $this->catalogException($exception);
            } catch (ConnectionException $exception) {
                $last = AiProviderException::fromConnection($exception);
            } catch (AiProviderException $exception) {
                $last = $exception;
            } catch (Throwable) {
                throw new AiProviderException('internal_response_processing_error');
            }
            if ($mode === 'json_object' || ! $this->mayFallback($last)) throw $last;
        }
        throw $last ?? new AiProviderException('structured_output_failed');
    }

    private function mayFallback(AiProviderException $exception): bool
    {
        return in_array($exception->diagnosticCode, ['provider_schema_rejected', 'provider_request_rejected', 'provider_malformed_json', 'structured_output_failed'], true);
    }

    private function decodeObject(mixed $content): array
    {
        if (is_array($content)) {
            $content = collect($content)->filter(fn ($part): bool => is_array($part) && in_array($part['type'] ?? null, ['text', 'output_text'], true))
                ->map(fn (array $part): string => (string) ($part['text'] ?? ''))->implode('');
        }
        if (! is_string($content) || trim($content) === '') throw new AiProviderException('structured_output_failed');
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? '');
        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
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
        // OpenRouter forwards the schema to the selected upstream provider.
        // Models such as Gemini reject otherwise-valid JSON Schema keywords
        // including pattern and maxLength. Laravel performs the complete,
        // authoritative document validation after generation, so the provider
        // receives only the portable structural subset.
        // OpenRouter forwards this contract to heterogeneous upstreams.
        // Laravel owns cardinality enforcement because nested minItems and
        // maxItems are rejected by some otherwise compatible providers.
        $supported = ['type', 'format', 'title', 'description', 'enum', 'items', 'prefixItems', 'minimum', 'maximum', 'anyOf', 'oneOf', 'properties', 'additionalProperties', 'required', '$id', '$defs', '$ref', '$anchor'];
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
