<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NavigationAsyncAddContractTest extends TestCase
{
    public function test_custom_public_links_are_added_without_a_page_navigation(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/staff/navigation.blade.php');

        $this->assertStringContainsString('data-navigation-custom-add', $view);
        $this->assertStringContainsString("customForm?.addEventListener('submit'", $view);
        $this->assertStringContainsString('event.preventDefault()', $view);
        $this->assertStringContainsString("headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }", $view);
        $this->assertStringContainsString('Object.values(result.errors ?? {}).flat()[0]', $view);
        $this->assertStringContainsString('appendPublicItem(result)', $view);
        $this->assertStringContainsString("node.querySelectorAll('[data-icon-picker]').forEach(bindIconPicker)", $view);
    }

    public function test_invalid_destinations_return_field_validation_instead_of_a_generic_abort(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NavigationWorkspaceController.php');

        $this->assertStringContainsString('throw ValidationException::withMessages([', $controller);
        $this->assertStringContainsString("'url' => 'Enter # for a parent-only menu, an internal path beginning with /, or a complete URL beginning with https://.'", $controller);
        $this->assertStringNotContainsString("abort_unless(str_starts_with(\$url", $controller);
    }

    public function test_hash_destination_is_a_group_only_public_parent(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NavigationWorkspaceController.php');
        $node = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/public-navigation-node.blade.php');
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/layouts/public.blade.php');
        $fields = file_get_contents(dirname(__DIR__, 2).'/resources/views/staff/navigation-fields.blade.php');

        $this->assertStringContainsString("\$url !== '#'", $controller);
        $this->assertStringContainsString("'open_new_tab' => \$url !== '#'", $controller);
        $this->assertStringContainsString("\$isGroupOnly = \$itemUrl === '#'", $node);
        $this->assertStringContainsString('@unless($isGroupOnly)', $node);
        $this->assertStringContainsString('workspace-nav-static', $node);
        $this->assertStringContainsString("where('url', '!=', '#')", $layout);
        $this->assertStringContainsString('Use # for a parent menu that only expands its child links.', $fields);
    }
}
