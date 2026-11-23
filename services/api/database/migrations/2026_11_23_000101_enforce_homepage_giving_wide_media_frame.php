<?php

use App\Services\HomepageStarterService;
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

        $homepage = DB::table('pages')->where('source_key', 'homepage')->orWhere('page_type', 'homepage')->first();
        if (! $homepage) {
            return;
        }

        $features = DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all();
        $document = app(HomepageStarterService::class)->document($features);

        DB::table('pages')->where('id', $homepage->id)->update([
            'blocks' => json_encode($document, JSON_THROW_ON_ERROR),
            'body' => app(PageDocumentService::class)->plainText($document, $features),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve staff-editable pre-launch defaults on rollback.
    }
};
