<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\FeatureSetting;
use App\Services\Library\PdfCoverGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_pdf_book_upload_does_not_require_separate_artwork_and_uses_generated_cover(): void
    {
        Storage::fake('local');
        FeatureSetting::where('key', 'books_library')->update(['state' => 'enabled']);
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $this->actingAs($admin);
        $this->mock(PdfCoverGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')->once()->with('local', \Mockery::type('string'))->andReturn('library/covers/generated.webp');
        });

        $this->post('/staff/content/books_library', [
            'title' => 'A PDF Book',
            'author' => 'Ada Author',
            'file' => UploadedFile::fake()->create('book.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('staff.content', ['library' => 'books_library']));

        $this->assertDatabaseHas('media_assets', [
            'library' => 'books_library',
            'title' => 'A PDF Book',
            'cover_image_path' => 'library/covers/generated.webp',
            'cover_image_alt' => 'First page of A PDF Book',
        ]);
    }

    public function test_category_and_tag_validation_use_their_own_dialog_error_bags(): void
    {
        FeatureSetting::where('key', 'books_library')->update(['state' => 'enabled']);
        $admin = app(InstallMinistry::class)->execute([
            'name' => 'Hope Community', 'domain' => 'hope.example.org', 'timezone' => 'Africa/Lagos',
            'location' => 'Main Campus', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@example.org',
            'password' => 'long-private-password',
        ]);
        $this->actingAs($admin);

        $this->post('/staff/content/categories', ['library' => 'books_library', 'category_name' => '  '])
            ->assertSessionHasErrorsIn('category', ['category_name']);
        $this->post('/staff/content/tags', ['library' => 'books_library', 'tag_name' => '  '])
            ->assertSessionHasErrorsIn('tag', ['tag_name']);
    }

    public function test_pdf_without_generated_artwork_is_served_inline_as_the_archive_cover(): void
    {
        Storage::fake('local');
        FeatureSetting::where('key', 'books_library')->update(['state' => 'enabled']);
        $id = (string) Str::uuid();
        Storage::disk('local')->put('library/books/book.pdf', '%PDF-1.4 test');
        DB::table('media_assets')->insert(['id' => $id, 'title' => 'PDF Book', 'provider' => 'local', 'provider_key' => 'library/books/book.pdf', 'library' => 'books_library', 'kind' => 'book', 'status' => 'published', 'published_at' => now(), 'file_disk' => 'local', 'file_path' => 'library/books/book.pdf', 'file_name' => 'book.pdf', 'file_mime' => 'application/pdf', 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('public.library.cover', ['books', $id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=book.pdf');
    }
}
