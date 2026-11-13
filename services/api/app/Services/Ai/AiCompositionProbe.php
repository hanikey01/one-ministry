<?php

namespace App\Services\Ai;

final class AiCompositionProbe
{
    public const VERSION = 2;

    public static function schema(): array
    {
        $schema = AiPageCompositionContract::schema(['heading', 'paragraph', 'button']);
        $schema['properties']['ready'] = ['type' => 'boolean'];
        $schema['required'][] = 'ready';
        $schema['properties']['sections']['minItems'] = 2;
        $schema['properties']['sections']['maxItems'] = 2;

        return $schema;
    }

    public static function validate(array $output): void
    {
        if (($output['ready'] ?? null) !== true || ! is_array($output['sections'] ?? null) || count($output['sections']) !== 2) throw new AiProviderException('structured_output_failed');
        unset($output['ready']);
        try {
            app(AiPageCompositionCompiler::class)->compile($output, []);
        } catch (\Throwable) {
            throw new AiProviderException('structured_output_failed');
        }
    }
}
