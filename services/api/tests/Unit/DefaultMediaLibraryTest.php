<?php

namespace Tests\Unit;

use App\Services\DefaultMediaLibrary;
use Tests\TestCase;

class DefaultMediaLibraryTest extends TestCase
{
    public function test_every_catalogued_default_media_source_is_packaged_with_accessible_metadata(): void
    {
        $catalog = app(DefaultMediaLibrary::class)->catalog();

        $this->assertCount(13, $catalog);
        foreach ($catalog as $source => $metadata) {
            $this->assertFileExists(public_path(ltrim($source, '/')));
            $this->assertNotSame('', trim($metadata['title']));
            $this->assertNotSame('', trim($metadata['alt']));
        }
    }
}
