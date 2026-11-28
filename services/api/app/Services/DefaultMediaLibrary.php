<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Installs the packaged starter artwork as normal ministry-owned media. */
final class DefaultMediaLibrary
{
    /** @return array<string,array{title:string,alt:string}> */
    public function catalog(): array
    {
        return [
            '/images/home/community-departments.png' => ['title' => 'Community and departments', 'alt' => 'People gathering and serving together in community.'],
            '/images/home/flyer-find-your-place.svg' => ['title' => 'Find your place flyer', 'alt' => 'Starter program flyer inviting people to find their place.'],
            '/images/home/flyer-grow-in-the-word.svg' => ['title' => 'Grow in the Word flyer', 'alt' => 'Starter program flyer inviting people to grow in the Word.'],
            '/images/home/flyer-night-of-worship.svg' => ['title' => 'Night of worship flyer', 'alt' => 'Starter flyer for a night of worship.'],
            '/images/home/next-steps.png' => ['title' => 'Ministry next steps', 'alt' => 'People taking a practical next step in ministry life.'],
            '/images/home/prayer-care.png' => ['title' => 'Prayer and care', 'alt' => 'A caring ministry conversation and prayer support.'],
            '/images/home/sermon-spotlight.png' => ['title' => 'Sermon spotlight', 'alt' => 'A ministry teaching and sermon setting.'],
            '/images/home/worship-hero.png' => ['title' => 'Worship gathering hero', 'alt' => 'People gathered for worship and ministry community.'],
            '/images/pages/community-outreach.png' => ['title' => 'Community outreach', 'alt' => 'Volunteers serving their community together.'],
            '/images/pages/giving-community-support.png' => ['title' => 'Giving with purpose', 'alt' => 'Volunteers providing practical support to their community.'],
            '/images/pages/home-arrival-hero.png' => ['title' => 'Ministry welcome', 'alt' => 'People arriving and receiving a warm ministry welcome.'],
            '/images/pages/home-community-carousel.png' => ['title' => 'Ministry community', 'alt' => 'People from different generations sharing ministry community.'],
            '/images/pages/home-service-carousel.png' => ['title' => 'Service and gathering', 'alt' => 'A welcoming ministry service and gathering.'],
        ];
    }

    /** @return array<string,string> Map of packaged source URL to public Media Library URL. */
    public function install(): array
    {
        $urls = [];
        foreach ($this->catalog() as $sourceUrl => $metadata) {
            $sourcePath = public_path(ltrim($sourceUrl, '/'));
            if (! is_file($sourcePath)) {
                throw new RuntimeException("Default media source is missing: {$sourceUrl}");
            }

            $storagePath = $this->storagePath($sourceUrl);
            $contents = file_get_contents($sourcePath);
            if ($contents === false) {
                throw new RuntimeException("Default media source cannot be read: {$sourceUrl}");
            }
            // Starter artwork is public by definition. Keeping the canonical
            // Media Library record while storing the bytes on the public disk
            // lets a configured web server serve them directly instead of
            // booting Laravel and querying the database for every image load.
            if (! Storage::disk('public')->exists($storagePath) && ! Storage::disk('public')->put($storagePath, $contents)) {
                throw new RuntimeException("Default media could not be installed: {$sourceUrl}");
            }

            $record = DB::table('media_assets')->where([
                'library' => 'media_library',
                'provider' => 'local',
                'provider_key' => $storagePath,
            ])->first();
            $id = $record?->id ?? (string) Str::uuid();
            $values = [
                'title' => $metadata['title'],
                'description' => 'Packaged starter artwork. Replace or reuse it from the ministry Media Library.',
                'provider' => 'local',
                'provider_key' => $storagePath,
                'library' => 'media_library',
                'kind' => 'image',
                'status' => 'published',
                'published_at' => $record?->published_at ?? now(),
                'file_disk' => 'public',
                'file_path' => $storagePath,
                'file_name' => basename($sourcePath),
                'file_mime' => $this->mimeType($sourcePath),
                'file_size_bytes' => strlen($contents),
                'alt_text' => $metadata['alt'],
                'updated_at' => now(),
            ];
            if ($record) {
                DB::table('media_assets')->where('id', $id)->update($values);
            } else {
                DB::table('media_assets')->insert(['id' => $id, ...$values, 'created_at' => now()]);
            }
            $urls[$sourceUrl] = route('public.storage', ['path' => $storagePath], false);
        }

        return $urls;
    }

    public function resolveUrl(?string $url): ?string
    {
        if (! is_string($url) || ! isset($this->catalog()[$url])) {
            return $url;
        }
        $id = DB::table('media_assets')->where([
            'library' => 'media_library',
            'provider' => 'local',
            'provider_key' => $this->storagePath($url),
            'status' => 'published',
        ])->value('id');

        return $id ? route('public.storage', ['path' => $this->storagePath($url)], false) : $url;
    }

    public function resolveDocument(array $document): array
    {
        $urls = [];
        foreach (array_keys($this->catalog()) as $sourceUrl) {
            $resolved = $this->resolveUrl($sourceUrl);
            if ($resolved !== $sourceUrl) {
                $urls[$sourceUrl] = $resolved;
            }
        }

        return $this->replaceUrls($document, $urls);
    }

    private function replaceUrls(mixed $value, array $urls): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->replaceUrls($item, $urls), $value);
        }

        return is_string($value) && $urls !== [] ? strtr($value, $urls) : $value;
    }

    private function storagePath(string $sourceUrl): string
    {
        return 'media-library/defaults/'.Str::after($sourceUrl, '/images/');
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
