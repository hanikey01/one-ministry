<?php

namespace App\Contracts;

interface AiTextProvider
{
    /** @return array<int, array{model_id:string, display_name:string, context_window:?int, supports_structured_output:bool, input_modalities:array<int,string>, output_modalities:array<int,string>, metadata:array<string,mixed>}> */
    public function discoverModels(string $apiKey): array;

    /** @return array{latency_ms:int} */
    public function validateConnection(string $apiKey): array;

    /** @return array{model:string, latency_ms:int, response_mode:string, output:array<string,mixed>} */
    public function validateStructuredOutput(string $apiKey, string $modelId): array;

    /** @param array<string, mixed> $schema
     *  @return array{model:string, latency_ms:int, response_mode:string, output:array<string,mixed>}
     */
    public function generateStructuredOutput(string $apiKey, string $modelId, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, int $maxOutputTokens): array;
}
