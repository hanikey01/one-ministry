<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MediaLibraryInteractionContractTest extends TestCase
{
    public function test_public_media_is_cacheable_and_default_artwork_uses_the_public_disk(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PublicMediaController.php');
        $storage = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PublicStorageController.php');
        $defaults = file_get_contents(dirname(__DIR__, 2).'/app/Services/DefaultMediaLibrary.php');

        $this->assertStringContainsString('$response->setAutoLastModified()', $controller);
        $this->assertStringContainsString('$response->setEtag(', $controller);
        $this->assertStringContainsString('stale-while-revalidate=604800', $storage);
        $this->assertStringContainsString("Storage::disk('public')->put", $defaults);
        $this->assertStringContainsString("'file_disk' => 'public'", $defaults);
        $this->assertStringContainsString("route('public.storage'", $defaults);
    }

    public function test_media_delete_action_is_reusable_and_modal_dismisses_outside(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/staff/media-library.blade.php');

        $this->assertStringContainsString('finally{button.disabled=false;}', $view);
        $this->assertStringContainsString('removeAttribute("disabled")', $view);
        $this->assertStringContainsString("event.target.matches?.('[data-media-modal]')", $view);
        $this->assertStringContainsString("event.key==='Escape'", $view);
    }

    public function test_rich_text_has_a_deliberate_heading_to_body_gap(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertMatchesRegularExpression('/\\.public-page-rich-text \\{[^}]*display: grid;[^}]*gap:/', $styles);
    }
}
