<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\PublicLibraryRoute;

class LibraryController extends Controller
{
    private const LIBRARIES = ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'];

    public function publicIndex(string $library): JsonResponse
    {
        $this->requireEnabled($library);
        if ($library === 'sermon_series') {
            return response()->json(['data' => DB::table('sermon_series')->where('status', 'published')->orderByDesc('created_at')->get()]);
        }

        return response()->json(['data' => $this->assets($library)->where('media_assets.status', 'published')->orderByDesc('media_assets.published_at')->get()->map(fn ($asset) => $this->publicAsset($asset))]);
    }

    public function publicSeries(string $series): JsonResponse
    {
        $this->requireEnabled('sermon_series');
        $item = DB::table('sermon_series')->where(['id' => $series, 'status' => 'published'])->first();
        abort_unless($item, 404);
        $messages = DB::table('media_assets')->where('sermon_series_id', $series)->where('status', 'published')->whereIn('library', ['audio_sermons', 'video_sermons'])->orderBy('position')->orderByDesc('published_at')->get()->map(fn ($asset) => $this->publicAsset($asset));

        return response()->json(['data' => ['series' => $item, 'messages' => $messages]]);
    }

    public function download(string $library, string $asset)
    {
        return $this->downloadPublished($library, $asset);
    }

    public function publicDownload(string $library, string $asset)
    {
        $libraryKey = PublicLibraryRoute::keyFor($library);
        abort_unless($libraryKey !== null, 404);

        return $this->downloadPublished($libraryKey, $asset, false);
    }

    public function publicStream(string $library, string $asset)
    {
        $libraryKey = PublicLibraryRoute::keyFor($library);
        abort_unless(in_array($libraryKey, ['audio_sermons', 'video_sermons'], true), 404);

        return $this->downloadPublished($libraryKey, $asset, true);
    }

    public function publicCover(string $library, string $asset)
    {
        $libraryKey = PublicLibraryRoute::keyFor($library);
        abort_unless($libraryKey !== null, 404);
        $this->requireEnabled($libraryKey);
        $item = $libraryKey === 'sermon_series'
            ? DB::table('sermon_series')->where(['id' => $asset, 'status' => 'published'])->first()
            : DB::table('media_assets')->where(['id' => $asset, 'library' => $libraryKey, 'status' => 'published'])->first();
        abort_unless($item, 404);
        if ($item->cover_image_path && Storage::disk('local')->exists($item->cover_image_path)) {
            return Storage::disk('local')->response($item->cover_image_path, null, ['Cache-Control' => 'public, max-age=86400'], 'inline');
        }
        abort_unless($libraryKey === 'books_library' && $item->file_mime === 'application/pdf' && $item->file_disk && $item->file_path, 404);
        $disk = Storage::disk($item->file_disk);
        abort_unless($disk->exists($item->file_path), 404);

        return $disk->response($item->file_path, $item->file_name, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'public, max-age=86400'], 'inline');
    }

    private function downloadPublished(string $library, string $asset, bool $inline = false)
    {
        $this->requireEnabled($library);
        $item = DB::table('media_assets')->where(['id' => $asset, 'library' => $library, 'status' => 'published'])->first();
        abort_unless($item && $item->file_disk && $item->file_path, 404);
        $disk = Storage::disk($item->file_disk);
        abort_unless($disk->exists($item->file_path), 404);
        $headers = $item->file_mime ? ['Content-Type' => $item->file_mime] : [];

        return $inline
            ? $disk->response($item->file_path, $item->file_name, $headers, 'inline')
            : $disk->download($item->file_path, $item->file_name ?: 'resource', $headers);
    }

    public function staffIndex(string $library): JsonResponse
    {
        $this->requireLibrary($library);

        return response()->json(['data' => $library === 'sermon_series' ? DB::table('sermon_series')->latest()->get() : $this->assets($library)->latest('media_assets.created_at')->get()]);
    }

    public function taxonomy(): JsonResponse
    {
        return response()->json(['data' => ['categories' => DB::table('library_categories')->orderBy('name')->get(), 'tags' => DB::table('library_tags')->orderBy('name')->get()]]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'library' => ['nullable', Rule::in(self::LIBRARIES)]]);
        $id = (string) Str::uuid();
        DB::table('library_categories')->insert(['id' => $id, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'library' => $data['library'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('library_categories')->where('id', $id)->first()], 201);
    }

    public function storeTag(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $id = (string) Str::uuid();
        DB::table('library_tags')->insert(['id' => $id, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('library_tags')->where('id', $id)->first()], 201);
    }

    public function store(Request $request, string $library): JsonResponse
    {
        $this->requireLibrary($library);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'provider' => ['nullable', 'string', 'max:40'], 'provider_key' => ['nullable', 'string', 'max:255'], 'youtube_url' => ['nullable', 'url', 'max:500'], 'speaker' => ['nullable', 'string', 'max:160'], 'scripture_reference' => ['nullable', 'string', 'max:255'], 'duration_seconds' => ['nullable', 'integer', 'min:0'], 'sermon_series_id' => ['nullable', 'uuid', 'exists:sermon_series,id'], 'library_category_id' => ['nullable', 'uuid', 'exists:library_categories,id'], 'tag_ids' => ['sometimes', 'array'], 'tag_ids.*' => ['uuid', 'exists:library_tags,id']]);
        $id = (string) Str::uuid();
        if ($library === 'sermon_series') {
            DB::table('sermon_series')->insert(['id' => $id, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => DB::table('sermon_series')->where('id', $id)->first()], 201);
        }
        $tags = $data['tag_ids'] ?? [];
        $youtubeUrl = $data['youtube_url'] ?? null;
        unset($data['tag_ids'], $data['youtube_url']);
        if ($youtubeUrl) {
            abort_unless($library === 'video_sermons', 422);
            $data['provider'] = 'youtube';
            $data['provider_key'] = $this->youtubeId($youtubeUrl);
        }
        DB::transaction(function () use ($data, $id, $library, $tags): void {
            DB::table('media_assets')->insert(['id' => $id, ...$data, 'provider' => $data['provider'] ?? 'external', 'provider_key' => $data['provider_key'] ?? $id, 'library' => $library, 'kind' => $library === 'books_library' ? 'book' : 'sermon', 'status' => 'draft', 'published_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            $this->attachTags($id, $tags);
        });

        return response()->json(['data' => $this->assets($library)->where('media_assets.id', $id)->first()], 201);
    }

    public function uploadBook(Request $request): JsonResponse
    {
        $this->requireEnabled('books_library');
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'file' => ['required', 'file', 'mimes:pdf,epub,doc,docx,mobi,azw,azw3,txt', 'max:102400'], 'library_category_id' => ['nullable', 'uuid', 'exists:library_categories,id'], 'tag_ids' => ['sometimes', 'array'], 'tag_ids.*' => ['uuid', 'exists:library_tags,id']]);
        $file = $request->file('file');
        $id = (string) Str::uuid();
        $path = $file->store('library/books', 'local');
        DB::transaction(function () use ($data, $file, $id, $path): void {
            DB::table('media_assets')->insert(['id' => $id, 'title' => $data['title'], 'provider' => 'local', 'provider_key' => $path, 'library' => 'books_library', 'kind' => 'book', 'status' => 'draft', 'library_category_id' => $data['library_category_id'] ?? null, 'file_disk' => 'local', 'file_path' => $path, 'file_name' => $file->getClientOriginalName(), 'file_mime' => $file->getMimeType(), 'file_size_bytes' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);
            $this->attachTags($id, $data['tag_ids'] ?? []);
        });

        return response()->json(['data' => $this->assets('books_library')->where('media_assets.id', $id)->first()], 201);
    }

    public function uploadMedia(Request $request, string $library): JsonResponse
    {
        abort_unless(in_array($library, ['audio_sermons', 'video_sermons'], true), 404);
        $this->requireEnabled($library);
        $mimes = $library === 'audio_sermons' ? 'mp3,m4a,wav,ogg,aac' : 'mp4,webm,mov,m4v';
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'file' => ['required', 'file', 'mimes:'.$mimes, 'max:512000'], 'description' => ['nullable', 'string'], 'speaker' => ['nullable', 'string', 'max:160'], 'scripture_reference' => ['nullable', 'string', 'max:255'], 'duration_seconds' => ['nullable', 'integer', 'min:0'], 'sermon_series_id' => ['nullable', 'uuid', 'exists:sermon_series,id'], 'library_category_id' => ['nullable', 'uuid', 'exists:library_categories,id'], 'tag_ids' => ['sometimes', 'array'], 'tag_ids.*' => ['uuid', 'exists:library_tags,id']]);
        $file = $request->file('file');
        $id = (string) Str::uuid();
        $path = $file->store('library/'.($library === 'audio_sermons' ? 'audio' : 'video'), 'local');
        DB::transaction(function () use ($data, $file, $id, $path, $library): void {
            DB::table('media_assets')->insert(['id' => $id, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'provider' => 'local', 'provider_key' => $path, 'library' => $library, 'kind' => 'sermon', 'status' => 'draft', 'speaker' => $data['speaker'] ?? null, 'scripture_reference' => $data['scripture_reference'] ?? null, 'duration_seconds' => $data['duration_seconds'] ?? null, 'sermon_series_id' => $data['sermon_series_id'] ?? null, 'library_category_id' => $data['library_category_id'] ?? null, 'file_disk' => 'local', 'file_path' => $path, 'file_name' => $file->getClientOriginalName(), 'file_mime' => $file->getMimeType(), 'file_size_bytes' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);
            $this->attachTags($id, $data['tag_ids'] ?? []);
        });

        return response()->json(['data' => $this->assets($library)->where('media_assets.id', $id)->first()], 201);
    }

    public function review(Request $request, string $library, string $item): JsonResponse
    {
        $this->requireLibrary($library);
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'rejected'])]]);
        $table = $library === 'sermon_series' ? 'sermon_series' : 'media_assets';
        $query = DB::table($table)->where('id', $item);
        if ($table === 'media_assets') {
            $query->where('library', $library);
        }
        abort_unless($query->where('status', 'draft')->exists(), 404);
        $changes = ['status' => $data['status'], 'updated_at' => now()];
        if ($table === 'media_assets') {
            $changes['published_at'] = $data['status'] === 'published' ? now() : null;
        }
        $query->update($changes);
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => 'library.item_reviewed', 'subject_type' => $table, 'subject_id' => $item, 'changes_json' => json_encode(['library' => $library, 'status' => $data['status']]), 'ip_address' => $request->ip(), 'created_at' => now()]);

        return response()->json(['data' => $query->first()]);
    }

    private function assets(string $library)
    {
        return DB::table('media_assets')->leftJoin('library_categories', 'library_categories.id', '=', 'media_assets.library_category_id')->select('media_assets.*', 'library_categories.name as category_name')->where('media_assets.library', $library);
    }

    private function publicAsset(object $asset): array
    {
        return [
            'id' => $asset->id,
            'title' => $asset->title,
            'description' => $asset->description,
            'library' => $asset->library,
            'kind' => $asset->kind,
            'speaker' => $asset->speaker,
            'scripture_reference' => $asset->scripture_reference,
            'duration_seconds' => $asset->duration_seconds,
            'category_name' => $asset->category_name ?? null,
            'published_at' => $asset->published_at,
            'delivery' => $asset->file_disk && $asset->file_path ? 'download' : ($asset->provider === 'youtube' ? 'youtube' : null),
            'youtube_id' => $asset->provider === 'youtube' ? $asset->provider_key : null,
            'file_name' => $asset->file_name,
        ];
    }

    private function attachTags(string $assetId, array $tagIds): void
    {
        foreach (array_unique($tagIds) as $tagId) {
            DB::table('library_asset_tags')->insertOrIgnore(['media_asset_id' => $assetId, 'library_tag_id' => $tagId]);
        }
    }

    private function youtubeId(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        parse_str($parts['query'] ?? '', $query);
        $id = $host === 'youtu.be' ? ltrim($parts['path'] ?? '', '/') : ($query['v'] ?? (preg_match('#/(?:embed|shorts)/([A-Za-z0-9_-]{11})#', $parts['path'] ?? '', $matches) ? $matches[1] : null));
        abort_unless(is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id), 422, 'Enter a valid YouTube video link.');

        return $id;
    }

    private function requireLibrary(string $library): void
    {
        abort_unless(in_array($library, self::LIBRARIES, true), 404);
    }

    private function requireEnabled(string $library): void
    {
        $this->requireLibrary($library);
        $modules = app(\App\Services\ModuleAvailability::class);
        $staffRoute = request()->is('staff/*') || request()->is('api/v1/staff/*');
        $available = $staffRoute
            ? $modules->visibleToStaff($library, request()->user())
            : $modules->enabled($library);
        abort_unless($available, 404);
    }
}
