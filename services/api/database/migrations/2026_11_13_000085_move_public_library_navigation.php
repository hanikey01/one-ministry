<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $paths = [
            '/resources/books' => '/library/books',
            '/resources/audio-sermons' => '/library/audio-sermons',
            '/resources/video-sermons' => '/library/video-sermons',
            '/resources/sermon-series' => '/library/sermon-series',
        ];

        foreach ($paths as $old => $new) {
            DB::table('navigation_items')->where('url', $old)->update(['url' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $paths = [
            '/library/books' => '/resources/books',
            '/library/audio-sermons' => '/resources/audio-sermons',
            '/library/video-sermons' => '/resources/video-sermons',
            '/library/sermon-series' => '/resources/sermon-series',
        ];

        foreach ($paths as $new => $old) {
            DB::table('navigation_items')->where('url', $new)->update(['url' => $old, 'updated_at' => now()]);
        }
    }
};
