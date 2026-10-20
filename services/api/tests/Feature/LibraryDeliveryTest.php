<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_enabled_library_can_expose_a_published_book_file(): void
    {
        Storage::fake('local');
        $id = (string) Str::uuid();
        Storage::disk('local')->put('library/books/hope.txt', 'resource text');
        FeatureSetting::where('key', 'books_library')->update(['state' => 'disabled']);
        DB::table('media_assets')->insert(['id' => $id, 'title' => 'Hope', 'provider' => 'local', 'provider_key' => 'library/books/hope.txt', 'library' => 'books_library', 'kind' => 'book', 'status' => 'published', 'published_at' => now(), 'file_disk' => 'local', 'file_path' => 'library/books/hope.txt', 'file_name' => 'hope.txt', 'file_mime' => 'text/plain', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson('/api/v1/public/libraries/books_library')->assertNotFound();
        $this->get("/api/v1/public/libraries/books_library/{$id}/download")->assertNotFound();

        FeatureSetting::where('key', 'books_library')->update(['state' => 'enabled']);
        $listing = $this->getJson('/api/v1/public/libraries/books_library')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.delivery', 'download');

        $asset = $listing->json('data.0');
        $this->assertArrayNotHasKey('file_path', $asset);
        $this->assertArrayNotHasKey('file_disk', $asset);
        $this->assertArrayNotHasKey('provider_key', $asset);
        $this->get("/api/v1/public/libraries/books_library/{$id}/download")->assertOk()->assertHeader('content-disposition', 'attachment; filename=hope.txt');
    }
}
