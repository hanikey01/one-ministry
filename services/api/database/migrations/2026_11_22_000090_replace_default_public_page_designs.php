<?php

use App\Services\AboutPageStarterService;
use App\Services\HomepageStarterService;
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

        $features = DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all();
        $documents = app(PageDocumentService::class);
        $homepage = app(HomepageStarterService::class)->document($features);
        $about = app(AboutPageStarterService::class)->document($features);
        $now = now();

        $homepageRecord = DB::table('pages')->where('source_key', 'homepage')->first();
        if ($homepageRecord) {
            DB::table('pages')->where('id', $homepageRecord->id)->update([
                'blocks' => json_encode($homepage, JSON_THROW_ON_ERROR),
                'body' => $documents->plainText($homepage, $features),
                'status' => 'published',
                'updated_at' => $now,
            ]);
        }

        $aboutRecord = DB::table('pages')->where('source_key', 'about')->orWhere('slug', 'about-us')->first();
        if ($aboutRecord) {
            DB::table('pages')->where('id', $aboutRecord->id)->update([
                'title' => 'About Us', 'slug' => 'about-us',
                'blocks' => json_encode($about, JSON_THROW_ON_ERROR),
                'body' => $documents->plainText($about, $features),
                'page_type' => 'standard', 'source_key' => 'about',
                'seo_title' => 'About Us',
                'seo_description' => 'Learn about our ministry, its story, values, people, and public life.',
                'status' => 'published',
                'published_at' => $aboutRecord->published_at ?: $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $actor = $homepageRecord?->created_by ?? DB::table('users')->orderBy('created_at')->value('id');
        if (! $actor) {
            return;
        }

        DB::table('pages')->insert([
            'id' => (string) Str::uuid(), 'title' => 'About Us', 'slug' => 'about-us',
            'body' => $documents->plainText($about, $features), 'blocks' => json_encode($about, JSON_THROW_ON_ERROR),
            'page_type' => 'standard', 'source_key' => 'about', 'seo_title' => 'About Us',
            'seo_description' => 'Learn about our ministry, its story, values, people, and public life.',
            'status' => 'published', 'created_by' => $actor, 'updated_by' => $actor, 'published_by' => $actor,
            'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Deliberately non-destructive: this release replaces pre-launch defaults.
    }
};
