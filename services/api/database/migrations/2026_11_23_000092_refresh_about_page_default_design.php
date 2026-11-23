<?php

use App\Services\AboutPageStarterService;
use App\Services\PageDocumentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('pages') || ! DB::getSchemaBuilder()->hasTable('feature_settings')) {
            return;
        }

        $about = DB::table('pages')->where('source_key', 'about')->orWhere('slug', 'about-us')->first();
        if (! $about) {
            return;
        }

        $features = DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all();
        $document = app(AboutPageStarterService::class)->document($features);

        DB::table('pages')->where('id', $about->id)->update([
            'blocks' => json_encode($document, JSON_THROW_ON_ERROR),
            'body' => app(PageDocumentService::class)->plainText($document, $features),
            'status' => 'published',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Deliberately non-destructive: this release refreshes an unlaunched default page.
    }
};
