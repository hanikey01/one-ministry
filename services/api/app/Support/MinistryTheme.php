<?php

namespace App\Support;

use App\Models\MinistrySetting;

class MinistryTheme
{
    /**
     * Platform-owned palettes. A ministry can select a family but cannot
     * alter page, panel, text, border, or action colours independently.
     *
     * @var array<string, array<string, string>>
     */
    public const PRESETS = [
        'neutral' => [
            'light_canvas' => '#F7F7F8', 'light_surface' => '#FFFFFF', 'light_raised' => '#FFFFFF',
            'light_ink' => '#17181A', 'light_muted' => '#61646B', 'light_line' => '#D9DCE1',
            'dark_canvas' => '#111214', 'dark_surface' => '#1A1C20', 'dark_raised' => '#22252A',
            'dark_ink' => '#F4F5F7', 'dark_muted' => '#AAADB5', 'dark_line' => '#383C44',
            'action' => '#343A40', 'chrome' => '#24262A',
        ],
        'tech_blue' => [
            'light_canvas' => '#F6F9FF', 'light_surface' => '#FFFFFF', 'light_raised' => '#FFFFFF',
            'light_ink' => '#10213D', 'light_muted' => '#53637C', 'light_line' => '#D7E2F3',
            'dark_canvas' => '#071426', 'dark_surface' => '#0C1D35', 'dark_raised' => '#132947',
            'dark_ink' => '#F3F7FF', 'dark_muted' => '#A8BAD4', 'dark_line' => '#29415F',
            'action' => '#2563EB', 'chrome' => '#0B1F3A',
        ],
        'warm' => [
            'light_canvas' => '#FAF8F5', 'light_surface' => '#FFFFFF', 'light_raised' => '#FFFEFC',
            'light_ink' => '#27221E', 'light_muted' => '#706860', 'light_line' => '#E3DCD4',
            'dark_canvas' => '#171411', 'dark_surface' => '#211D19', 'dark_raised' => '#2B2621',
            'dark_ink' => '#FAF6F1', 'dark_muted' => '#B7ACA3', 'dark_line' => '#443C35',
            'action' => '#6B5140', 'chrome' => '#332A23',
        ],
    ];

    private const STATUS_COLORS = [
        'success' => '#15803D',
        'warning' => '#B45309',
        'danger' => '#B91C1C',
    ];

    public static function cssVariables(?MinistrySetting $settings): string
    {
        $values = self::tokens($settings);

        return collect($values)
            ->map(fn (string $value, string $property): string => $property.':'.$value)
            ->implode('; ');
    }

    /** @return array<string, mixed> */
    public static function publicTheme(?MinistrySetting $settings): array
    {
        $tokens = self::tokens($settings);

        return [
            'preset' => $settings?->theme_preset ?? 'tech_blue',
            'chrome_mode' => $settings?->chrome_mode ?? 'preset',
            'chrome_color' => $tokens['--theme-chrome-color'],
            'chrome_foreground' => $tokens['--theme-chrome-foreground'],
            'light' => [
                'canvas' => $tokens['--theme-light-canvas-color'],
                'surface' => $tokens['--theme-light-surface-color'],
                'raised' => $tokens['--theme-light-raised-color'],
                'ink' => $tokens['--theme-light-ink'],
                'muted' => $tokens['--theme-light-muted'],
                'line' => $tokens['--theme-light-line'],
            ],
            'dark' => [
                'canvas' => $tokens['--theme-dark-canvas-color'],
                'surface' => $tokens['--theme-dark-surface-color'],
                'raised' => $tokens['--theme-dark-raised-color'],
                'ink' => $tokens['--theme-dark-ink'],
                'muted' => $tokens['--theme-dark-muted'],
                'line' => $tokens['--theme-dark-line'],
            ],
        ];
    }

    /** @return array<string, string> */
    private static function tokens(?MinistrySetting $settings): array
    {
        $preset = self::PRESETS[$settings?->theme_preset ?? 'tech_blue'] ?? self::PRESETS['tech_blue'];
        $customSeed = $settings?->chrome_mode === 'custom' && $settings?->chrome_color
            ? strtoupper($settings->chrome_color)
            : null;

        if ($customSeed !== null) {
            $preset = self::paletteFromSeed($customSeed);
        }

        $chrome = $customSeed ?? $preset['chrome'];

        $values = [
            '--theme-preset' => $settings?->theme_preset ?? 'tech_blue',
            '--theme-light-canvas-color' => $preset['light_canvas'],
            '--theme-light-surface-color' => $preset['light_surface'],
            '--theme-light-raised-color' => $preset['light_raised'],
            '--theme-light-ink' => $preset['light_ink'],
            '--theme-light-surface-ink' => $preset['light_ink'],
            '--theme-light-muted' => $preset['light_muted'],
            '--theme-light-line' => $preset['light_line'],
            '--theme-dark-canvas-color' => $preset['dark_canvas'],
            '--theme-dark-surface-color' => $preset['dark_surface'],
            '--theme-dark-raised-color' => $preset['dark_raised'],
            '--theme-dark-ink' => $preset['dark_ink'],
            '--theme-dark-surface-ink' => $preset['dark_ink'],
            '--theme-dark-muted' => $preset['dark_muted'],
            '--theme-dark-line' => $preset['dark_line'],
            '--theme-action-color' => $preset['action'],
            // Status states are platform-owned semantic tokens. They are not
            // editable ministry colours, and keeping them here prevents UI
            // components from carrying their own literal colour fallbacks.
            '--theme-success-color' => self::STATUS_COLORS['success'],
            '--theme-warning-color' => self::STATUS_COLORS['warning'],
            '--theme-danger-color' => self::STATUS_COLORS['danger'],
            '--theme-chrome-color' => $chrome,
            '--theme-chrome-foreground' => self::bestTextColor($chrome),
            '--theme-chrome-hover-color' => self::mix($chrome, self::bestTextColor($chrome), 0.12),
            '--theme-chrome-active-color' => self::mix($chrome, self::bestTextColor($chrome), 0.2),
            '--theme-focus-color' => $preset['action'],
            '--theme-selected-color' => self::mix($preset['action'], $preset['light_surface'], 0.88),
        ];

        // Legacy aliases keep existing components on the shared token path while
        // their page-specific styles are consolidated.
        $values['--theme-primary-color'] = $values['--theme-action-color'];
        $values['--theme-accent-color'] = $values['--theme-action-color'];
        $values['--theme-light-sidebar-color'] = $chrome;
        $values['--theme-dark-sidebar-color'] = $chrome;
        $values['--theme-light-sidebar-ink'] = $values['--theme-chrome-foreground'];
        $values['--theme-dark-sidebar-ink'] = $values['--theme-chrome-foreground'];
        $values['--brand-foreground'] = self::bestTextColor($values['--theme-action-color']);
        $values['--accent-foreground'] = $values['--brand-foreground'];
        $values['--action-foreground'] = $values['--brand-foreground'];
        $values['--success'] = $values['--theme-success-color'];
        $values['--warning'] = $values['--theme-warning-color'];
        $values['--danger'] = $values['--theme-danger-color'];
        $values['--theme-brand-text-light'] = self::accessibleBrandText($values['--theme-action-color'], $values['--theme-light-surface-color']);
        $values['--theme-brand-text-dark'] = self::accessibleBrandText($values['--theme-action-color'], $values['--theme-dark-surface-color']);
        $values['--theme-action-text-light'] = $values['--theme-brand-text-light'];
        $values['--theme-action-text-dark'] = $values['--theme-brand-text-dark'];

        return $values;
    }

    /** @return array<string, string> */
    private static function paletteFromSeed(string $seed): array
    {
        $lightSurface = self::mix($seed, '#FFFFFF', 0.992);
        $lightCanvas = self::mix($seed, '#FFFFFF', 0.965);
        $lightInk = self::adjustForContrast(self::mix($seed, '#000000', 0.78), $lightSurface, 7.0);
        $darkCanvas = self::mix($seed, '#000000', 0.92);
        $darkSurface = self::mix($seed, '#000000', 0.86);
        $darkRaised = self::mix($seed, '#000000', 0.79);
        $darkInk = self::adjustForContrast(self::mix($seed, '#FFFFFF', 0.82), $darkSurface, 7.0);

        return [
            'light_canvas' => $lightCanvas,
            'light_surface' => $lightSurface,
            'light_raised' => '#FFFFFF',
            'light_ink' => $lightInk,
            'light_muted' => self::adjustForContrast(self::mix($lightInk, $lightSurface, 0.42), $lightSurface, 4.5),
            'light_line' => self::mix($lightInk, $lightSurface, 0.86),
            'dark_canvas' => $darkCanvas,
            'dark_surface' => $darkSurface,
            'dark_raised' => $darkRaised,
            'dark_ink' => $darkInk,
            'dark_muted' => self::adjustForContrast(self::mix($darkInk, $darkSurface, 0.42), $darkSurface, 4.5),
            'dark_line' => self::mix($darkInk, $darkSurface, 0.82),
            'action' => $seed,
            'chrome' => $seed,
        ];
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

    private static function adjustForContrast(string $color, string $background, float $minimum): string
    {
        if (self::contrastRatio($color, $background) >= $minimum) {
            return $color;
        }

        $candidates = [];
        foreach (['#000000', '#FFFFFF'] as $target) {
            $low = 0.0;
            $high = 1.0;
            for ($i = 0; $i < 16; $i++) {
                $amount = ($low + $high) / 2;
                $candidate = self::mix($color, $target, $amount);
                if (self::contrastRatio($candidate, $background) >= $minimum) {
                    $high = $amount;
                } else {
                    $low = $amount;
                }
            }

            $candidate = self::mix($color, $target, $high);
            if (self::contrastRatio($candidate, $background) >= $minimum) {
                $candidates[] = [$candidate, $high];
            }
        }

        usort($candidates, fn (array $left, array $right): int => $left[1] <=> $right[1]);

        return $candidates[0][0] ?? self::bestTextColor($background);
    }

    private static function mix(string $from, string $to, float $amount): string
    {
        $amount = min(1, max(0, $amount));
        $start = array_map('hexdec', str_split(ltrim($from, '#'), 2));
        $end = array_map('hexdec', str_split(ltrim($to, '#'), 2));
        $mixed = array_map(fn (int $channel, int $target): int => (int) round($channel + (($target - $channel) * $amount)), $start, $end);

        return sprintf('#%02X%02X%02X', ...$mixed);
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
