<?php

namespace App\Services\Ai;

use App\Contracts\AiTextProvider;
use InvalidArgumentException;

final class AiProviderRegistry
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return ['gemini' => 'Google Gemini', 'openrouter' => 'OpenRouter'];
    }

    public function get(string $provider): AiTextProvider
    {
        return match ($provider) {
            'gemini' => app(GeminiTextProvider::class),
            'openrouter' => app(OpenRouterTextProvider::class),
            default => throw new InvalidArgumentException('Unsupported AI provider.'),
        };
    }
}
