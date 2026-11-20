<?php

namespace Tests\Unit;

use App\Models\MinistrySetting;
use App\Support\MinistryTheme;
use PHPUnit\Framework\TestCase;

class MinistryThemeTest extends TestCase
{
    public function test_custom_ministry_colour_generates_a_complete_accessible_palette(): void
    {
        $settings = new MinistrySetting([
            'theme_preset' => 'tech_blue',
            'chrome_mode' => 'custom',
            'chrome_color' => '#C2410C',
        ]);

        $theme = MinistryTheme::publicTheme($settings);
        $variables = $this->variables(MinistryTheme::cssVariables($settings));

        $this->assertSame('#C2410C', $theme['chrome_color']);
        $this->assertNotSame('#F6F9FF', $theme['light']['canvas']);
        $this->assertNotSame('#071426', $theme['dark']['canvas']);
        $this->assertSame('#C2410C', $variables['--theme-action-color']);
        $this->assertSame('#15803D', $variables['--theme-success-color']);
        $this->assertSame('#B45309', $variables['--theme-warning-color']);
        $this->assertSame('#B91C1C', $variables['--theme-danger-color']);
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($variables['--theme-brand-text-light'], $theme['light']['surface']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($variables['--theme-brand-text-dark'], $theme['dark']['surface']));
    }

    /** @return array<string, string> */
    private function variables(string $style): array
    {
        preg_match_all('/(--[a-z-]+):([^;]+)/', $style, $matches, PREG_SET_ORDER);

        return array_column($matches, 2, 1);
    }

    private function contrast(string $first, string $second): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
            $linear = fn (float $value): float => $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $linear($channels[0] / 255) + 0.7152 * $linear($channels[1] / 255) + 0.0722 * $linear($channels[2] / 255);
        };
        $one = $luminance($first);
        $two = $luminance($second);

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }
}
