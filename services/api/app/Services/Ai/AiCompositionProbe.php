<?php

namespace App\Services\Ai;

use Illuminate\Validation\ValidationException;
use Throwable;

final class AiCompositionProbe
{
    public const VERSION = 8;

    public static function schema(): array
    {
        return AiCanvasSectionService::schema();
    }

    public static function validate(array $output): void
    {
        try {
            app(AiCanvasSectionService::class)->normaliseSection($output['section'] ?? null);
        } catch (ValidationException $exception) {
            $code = $exception->errors()['composition'][0] ?? 'structured_output_failed';
            throw new AiProviderException(is_string($code) ? $code : 'structured_output_failed');
        } catch (Throwable) {
            throw new AiProviderException('structured_output_failed');
        }
    }
}
