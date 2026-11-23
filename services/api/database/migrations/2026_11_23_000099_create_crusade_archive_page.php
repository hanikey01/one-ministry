<?php

use App\Services\CrusadeArchiveStarterService;
use App\Services\PageDocumentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('pages') || ! DB::getSchemaBuilder()->hasTable('feature_settings')) {
            return;
        }

        if (DB::table('pages')->where('source_key', 'crusade_archive')->orWhere('slug', 'crusade-archive')->exists()) {
            return;
        }

        $actor = DB::table('users')->orderBy('created_at')->value('id');
        if (! $actor) {
            return;
        }

        $features = DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all();
        $document = app(CrusadeArchiveStarterService::class)->document($features);
        $now = now();

        DB::table('pages')->insert([
            'id' => (string) Str::uuid(),
            'title' => 'Ministry Crusade',
            'slug' => 'crusade-archive',
            'body' => app(PageDocumentService::class)->plainText($document, $features),
            'blocks' => json_encode($document, JSON_THROW_ON_ERROR),
            'page_type' => 'standard',
            'source_key' => 'crusade_archive',
            'seo_title' => 'Ministry Crusade Archive',
            'seo_description' => 'A private, editable archive for verified crusade photos and recordings.',
            'status' => 'draft',
            'created_by' => $actor,
            'updated_by' => $actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Preserve any staff-added archive material on rollback.
    }
};
