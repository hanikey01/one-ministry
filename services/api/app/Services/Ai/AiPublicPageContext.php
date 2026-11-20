<?php

namespace App\Services\Ai;

use App\Models\MinistrySetting;
use App\Models\User;
use App\Services\PermissionChecker;
use Illuminate\Support\Facades\DB;

final class AiPublicPageContext
{
    public function snapshot(User $actor): array
    {
        abort_unless(app(PermissionChecker::class)->allows($actor, 'content.manage'), 403);
        $settings = MinistrySetting::query()->first(['name', 'tagline', 'contact_email', 'contact_phone', 'public_address']);
        $media = DB::table('media_assets')->where('library', 'media_library')->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where('provider', 'local')->whereNotNull('file_path')->orderBy('id')->limit(60)
            ->get(['id', 'title', 'description', 'file_mime'])->mapWithKeys(function ($asset): array {
                $kind = match (true) {
                    str_starts_with($asset->file_mime ?? '', 'image/') => 'image',
                    str_starts_with($asset->file_mime ?? '', 'video/') => 'video',
                    str_starts_with($asset->file_mime ?? '', 'audio/') => 'audio',
                    default => 'file',
                };

                return [$asset->id => ['id' => $asset->id, 'title' => $asset->title, 'description' => $asset->description, 'kind' => $kind, 'url' => route('public.media', $asset->id)]];
            })->all();

        return ['ministry' => $settings?->toArray() ?? [], 'media' => $media];
    }

    public function forPrompt(array $snapshot): array
    {
        return ['ministry' => $snapshot['ministry'], 'media' => array_values(array_map(fn (array $item): array => array_diff_key($item, ['url' => true]), $snapshot['media']))];
    }

    public function forDesignPrompt(array $snapshot): array
    {
        return ['ministry' => $snapshot['ministry'], 'media' => array_values(array_map(function (array $item): array {
            $path = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH);

            return [...array_diff_key($item, ['url' => true]), 'path' => is_string($path) ? $path : ''];
        }, $snapshot['media']))];
    }

    /** @return array<int,string> */
    public function approvedMediaPaths(array $snapshot): array
    {
        return array_values(array_filter(array_map(fn (array $item): mixed => parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH), $snapshot['media']), 'is_string'));
    }
}
