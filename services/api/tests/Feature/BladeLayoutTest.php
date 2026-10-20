<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class BladeLayoutTest extends TestCase
{
    public function test_workspace_layout_renders_semantic_navigation_and_mobile_drawer_controls(): void
    {
        $hotFile = storage_path('framework/testing-vite.hot');
        file_put_contents($hotFile, 'http://localhost:5173');
        Vite::useHotFile($hotFile);

        $html = Blade::render(<<<'BLADE'
            <x-layouts.workspace title="People" ministry-name="Living Hope" user-name="Ada Admin" user-initials="AA">
                <x-slot:navigation><a href="/staff/people" aria-current="page">People</a></x-slot:navigation>
                <h1>People</h1>
            </x-layouts.workspace>
        BLADE);

        $this->assertStringContainsString('Ministry workspace', $html);
        $this->assertStringContainsString('aria-label="Staff navigation"', $html);
        $this->assertStringContainsString('aria-label="Open navigation"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('People</h1>', $html);

        @unlink($hotFile);
    }
}
