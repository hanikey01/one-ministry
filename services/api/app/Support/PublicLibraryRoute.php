<?php

namespace App\Support;

final class PublicLibraryRoute
{
    /** @var array<string, string> */
    public const SLUGS = [
        'books' => 'books_library',
        'audio-sermons' => 'audio_sermons',
        'video-sermons' => 'video_sermons',
        'sermon-series' => 'sermon_series',
    ];

    public static function keyFor(string $slug): ?string
    {
        return self::SLUGS[$slug] ?? null;
    }

    public static function slugFor(string $key): ?string
    {
        return array_search($key, self::SLUGS, true) ?: null;
    }
}
