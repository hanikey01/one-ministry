<?php

namespace App\Services\Ai;

final class AiCompositionProbe
{
    public const VERSION = 7;

    public static function schema(): array
    {
        return AiCanvasSectionService::schema();
    }

    public static function validate(array $output): void
    {
        try {
            app(AiCanvasSectionService::class)->normaliseSection($output['section'] ?? null);
        } catch (\Throwable) {
            throw new AiProviderException('structured_output_failed');
        }
    }
}
