<?php

namespace App\Support;

use App\Models\MinistrySetting;

class MinistryTheme
{
    public const DEFAULTS = [
        'primary_color' => '#343A40',
        'accent_color' => '#697386',
        'light_canvas_color' => '#F7F7F8',
        'light_surface_color' => '#FFFFFF',
        'light_sidebar_color' => '#24262A',
        'dark_canvas_color' => '#121315',
        'dark_surface_color' => '#1C1D20',
        'dark_sidebar_color' => '#0B0C0E',
    ];

    public static function cssVariables(?MinistrySetting $settings): string
    {
        $values = self::tokens($settings);

        return collect($values)
            ->map(fn (string $value, string $property): string => $property.':'.$value)
            ->implode('; ');
    }

    /** @return array<string, mixed> */
    public static function colors(?MinistrySetting $settings): array
    {
        $tokens = self::tokens($settings);

        return [
            'primary' => $tokens['--theme-primary-color'],
            'accent' => $tokens['--theme-accent-color'],
            'primary_foreground' => $tokens['--brand-foreground'],
            'accent_foreground' => $tokens['--accent-foreground'],
            'primary_text_light' => $tokens['--theme-brand-text-light'],
            'primary_text_dark' => $tokens['--theme-brand-text-dark'],
            'light' => [
                'canvas' => $tokens['--theme-light-canvas-color'],
                'surface' => $tokens['--theme-light-surface-color'],
                'sidebar' => $tokens['--theme-light-sidebar-color'],
                'canvas_foreground' => $tokens['--theme-light-ink'],
                'surface_foreground' => $tokens['--theme-light-surface-ink'],
                'sidebar_foreground' => $tokens['--theme-light-sidebar-ink'],
            ],
            'dark' => [
                'canvas' => $tokens['--theme-dark-canvas-color'],
                'surface' => $tokens['--theme-dark-surface-color'],
                'sidebar' => $tokens['--theme-dark-sidebar-color'],
                'canvas_foreground' => $tokens['--theme-dark-ink'],
                'surface_foreground' => $tokens['--theme-dark-surface-ink'],
                'sidebar_foreground' => $tokens['--theme-dark-sidebar-ink'],
            ],
        ];
    }

    /** @return array<string, string> */
    private static function tokens(?MinistrySetting $settings): array
    {
        $values = [];

        foreach (self::DEFAULTS as $key => $default) {
            $values['--theme-'.str_replace('_', '-', $key)] = strtoupper((string) ($settings?->{$key} ?: $default));
        }

        $values['--brand-foreground'] = self::bestTextColor($values['--theme-primary-color']);
        $values['--accent-foreground'] = self::bestTextColor($values['--theme-accent-color']);
        $values['--theme-brand-text-light'] = self::accessibleBrandText($values['--theme-primary-color'], $values['--theme-light-surface-color']);
        $values['--theme-brand-text-dark'] = self::accessibleBrandText($values['--theme-primary-color'], $values['--theme-dark-surface-color']);
        $values['--theme-light-ink'] = self::bestTextColor($values['--theme-light-canvas-color']);
        $values['--theme-light-surface-ink'] = self::bestTextColor($values['--theme-light-surface-color']);
        $values['--theme-light-sidebar-ink'] = self::bestTextColor($values['--theme-light-sidebar-color']);
        $values['--theme-dark-ink'] = self::bestTextColor($values['--theme-dark-canvas-color']);
        $values['--theme-dark-surface-ink'] = self::bestTextColor($values['--theme-dark-surface-color']);
        $values['--theme-dark-sidebar-ink'] = self::bestTextColor($values['--theme-dark-sidebar-color']);

        return $values;
    }

    private static function bestTextColor(string $hex): string
    {
        $rgb = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $luminance = 0.2126 * self::linear($rgb[0] / 255)
            + 0.7152 * self::linear($rgb[1] / 255)
            + 0.0722 * self::linear($rgb[2] / 255);

        // For every opaque sRGB hex accent, choosing the stronger of pure black
        // and white guarantees at least 4.58:1 contrast for normal text.
        $whiteContrast = 1.05 / ($luminance + 0.05);
        $blackContrast = ($luminance + 0.05) / 0.05;

        return $whiteContrast >= $blackContrast ? '#FFFFFF' : '#000000';
    }

    private static function accessibleBrandText(string $brand, string $background): string
    {
        if (self::contrastRatio($brand, $background) >= 4.5) {
            return $brand;
        }

        $rgb = array_map('hexdec', str_split(ltrim($brand, '#'), 2));
        $candidates = [];

        foreach ([[0, 0, 0], [255, 255, 255]] as $target) {
            $low = 0.0;
            $high = 1.0;

            for ($i = 0; $i < 16; $i++) {
                $amount = ($low + $high) / 2;
                $mixed = array_map(fn (int $channel, int $end): int => (int) round($channel + (($end - $channel) * $amount)), $rgb, $target);
                $hex = sprintf('#%02X%02X%02X', ...$mixed);

                if (self::contrastRatio($hex, $background) >= 4.5) {
                    $high = $amount;
                } else {
                    $low = $amount;
                }
            }

            $mixed = array_map(fn (int $channel, int $end): int => (int) round($channel + (($end - $channel) * $high)), $rgb, $target);
            $candidates[] = [sprintf('#%02X%02X%02X', ...$mixed), $high];
        }

        usort($candidates, fn (array $left, array $right): int => $left[1] <=> $right[1]);

        return $candidates[0][0];
    }

    private static function contrastRatio(string $foreground, string $background): float
    {
        $luminance = fn (string $hex): float => (function (array $rgb): float {
            return 0.2126 * self::linear($rgb[0] / 255)
                + 0.7152 * self::linear($rgb[1] / 255)
                + 0.0722 * self::linear($rgb[2] / 255);
        })(array_map('hexdec', str_split(ltrim($hex, '#'), 2)));
        $first = $luminance($foreground);
        $second = $luminance($background);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    private static function linear(float $channel): float
    {
        return $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
