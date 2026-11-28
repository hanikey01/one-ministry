<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ViewerTimeBladeTest extends TestCase
{
    public function test_viewer_time_component_and_public_surfaces_compile(): void
    {
        $paths = [
            'views/components/ui/viewer-time.blade.php',
            'views/public/service-times.blade.php',
            'views/public/home.blade.php',
            'views/public/partials/page-element.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(resource_path($path));
            $this->assertIsString($source);
            token_get_all(Blade::compileString($source), TOKEN_PARSE);
        }
    }
}
