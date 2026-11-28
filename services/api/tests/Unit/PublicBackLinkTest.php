<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PublicBackLinkTest extends TestCase
{
    public function test_public_detail_pages_share_the_styled_return_action(): void
    {
        $views = [
            'department.blade.php',
            'event.blade.php',
            'location.blade.php',
            'sermon-series.blade.php',
            'testimony.blade.php',
        ];

        foreach ($views as $view) {
            $source = file_get_contents(resource_path('views/public/'.$view));

            $this->assertIsString($source);
            $this->assertStringContainsString('class="public-back-link"', $source, $view);
            $this->assertStringContainsString('name="arrow-left"', $source, $view);

            token_get_all(Blade::compileString($source), TOKEN_PARSE);
        }

        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('.public-back-link {', $styles);
        $this->assertStringContainsString('.public-back-link:focus-visible', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion:reduce)', $styles);
    }
}
