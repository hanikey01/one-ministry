<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\Library\PdfCoverGenerator;
use App\Services\ModuleAvailability;
use App\Services\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Staff browser workflow for public books and sermon resources. */
class StaffContentLibraryController extends Controller
{
    private const LIBRARIES = ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'];

    public function index(Request $request, ModuleAvailability $modules, PermissionChecker $permissions): View
    {
        $libraries = array_values(array_filter(self::LIBRARIES, fn (string $library): bool => $modules->visibleToStaff($library, $request->user())));
        abort_if($libraries === [], 404);
        $library = in_array($request->query('library'), $libraries, true) ? $request->query('library') : $libraries[0];
        $query = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['draft', 'published', 'rejected'], true) ? $request->query('status') : '';

        if ($library === 'sermon_series') {
            $items = DB::table('sermon_series')
                ->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
                ->when($status !== '', fn ($builder) => $builder->where('status', $status))
                ->orderByDesc('created_at')->paginate(15)->withQueryString();
        } else {
            $items = DB::table('media_assets')
                ->leftJoin('library_categories', 'library_categories.id', '=', 'media_assets.library_category_id')
                ->where('media_assets.library', $library)
                ->when($query !== '', fn ($builder) => $builder->where(fn ($inner) => $inner->where('media_assets.title', 'like', "%{$query}%")->orWhere('media_assets.speaker', 'like', "%{$query}%")->orWhere('media_assets.author', 'like', "%{$query}%")))
                ->when($status !== '', fn ($builder) => $builder->where('media_assets.status', $status))
                ->select('media_assets.*', 'library_categories.name as category_name')
                ->orderByDesc('media_assets.created_at')->paginate(15)->withQueryString();
        }

        $assetTagIds = $library === 'sermon_series'
            ? collect()
            : DB::table('library_asset_tags')
                ->whereIn('media_asset_id', $items->pluck('id'))
                ->get()
                ->groupBy('media_asset_id')
                ->map(fn ($assetTags) => $assetTags->pluck('library_tag_id')->all());

        return view('staff.content', [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(), 'libraries' => $libraries, 'library' => $library, 'items' => $items,
            'query' => $query, 'status' => $status,
            'categories' => DB::table('library_categories')->where(fn ($builder) => $builder->whereNull('library')->orWhere('library', $library))->orderBy('name')->get(),
            'tags' => DB::table('library_tags')->orderBy('name')->get(),
            'assetTagIds' => $assetTagIds,
            'series' => DB::table('sermon_series')->orderByDesc('created_at')->get(['id', 'title']),
            'canPublish' => $permissions->allows($request->user(), 'content.publish'),
        ]);
    }

    public function store(Request $request, string $library, PdfCoverGenerator $pdfCovers): RedirectResponse
    {
        $this->library($library, $request);
        if ($library === 'sermon_series') return $this->storeSeries($request);

        $base = $this->assetRules($library);
        $rules = [...$base];
        if ($library === 'books_library') { $rules['file'] = ['required', 'file', 'mimes:pdf,epub,doc,docx,mobi,azw,azw3,txt', 'max:102400']; }
        if ($library === 'audio_sermons') $rules['file'] = ['required', 'file', 'mimes:mp3,m4a,wav,ogg,aac', 'max:512000'];
        if ($library === 'video_sermons') { $rules['file'] = ['nullable', 'file', 'mimes:mp4,webm,mov,m4v', 'max:512000']; $rules['youtube_url'] = ['nullable', 'url', 'max:500', 'required_without:file']; }
        $data = $request->validate($rules);
        abort_unless($library !== 'video_sermons' || $request->hasFile('file') || filled($data['youtube_url'] ?? null), 422);

        $id = (string) Str::uuid();
        $file = $request->file('file');
        $isYoutube = filled($data['youtube_url'] ?? null);
        $path = $file?->store('library/'.match ($library) {'books_library' => 'books', 'audio_sermons' => 'audio', default => 'video'}, 'local');
        $coverPath = $request->file('cover_image')?->store('library/covers', 'local');
        if ($library === 'books_library' && ! $coverPath && $file?->extension() === 'pdf' && $path) {
            $coverPath = $pdfCovers->generate('local', $path);
            if ($coverPath && blank($data['cover_image_alt'] ?? null)) $data['cover_image_alt'] = 'First page of '.$data['title'];
        }
        $tags = $data['tag_ids'] ?? [];
        unset($data['tag_ids'], $data['youtube_url'], $data['file'], $data['cover_image']);
        DB::transaction(function () use ($request, $data, $id, $library, $file, $path, $coverPath, $isYoutube, $tags): void {
            DB::table('media_assets')->insert([
                'id' => $id, ...$data, 'library' => $library, 'kind' => $library === 'books_library' ? 'book' : 'sermon', 'status' => 'draft',
                'provider' => $isYoutube ? 'youtube' : 'local', 'provider_key' => $isYoutube ? $this->youtubeId((string) $request->input('youtube_url')) : $path,
                'file_disk' => $file ? 'local' : null, 'file_path' => $path, 'file_name' => $file?->getClientOriginalName(), 'file_mime' => $file?->getMimeType(), 'file_size_bytes' => $file?->getSize(),
                'cover_image_path' => $coverPath,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncTags($id, $tags);
            $this->audit($request, 'library.item_created', 'media_assets', $id, ['library' => $library]);
        });

        return $this->back($library, 'Draft content added. Submit it for review when it is ready.');
    }

    public function update(Request $request, string $library, string $item): RedirectResponse
    {
        $this->library($library, $request);
        if ($library === 'sermon_series') return $this->updateSeries($request, $item);
        $record = DB::table('media_assets')->where(['id' => $item, 'library' => $library])->first(); abort_unless($record, 404);
        $data = $request->validate($this->assetRules($library, true));
        $coverPath = $request->file('cover_image')?->store('library/covers', 'local');
        unset($data['cover_image']);
        if ($coverPath) $data['cover_image_path'] = $coverPath;
        $data += ['duration_seconds' => null];
        if ($library === 'books_library') $data += ['speaker' => null, 'sermon_series_id' => null];
        DB::transaction(function () use ($request, $data, $item, $record, $coverPath): void { DB::table('media_assets')->where('id', $item)->update([...collect($data)->except('tag_ids')->all(), 'updated_at' => now()]); if ($coverPath && $record->cover_image_path) Storage::disk('local')->delete($record->cover_image_path); if ($request->has('tag_ids')) $this->syncTags($item, $data['tag_ids'] ?? []); $this->audit($request, 'library.item_updated', 'media_assets', $item); });
        return $this->back($library, 'Content details saved.');
    }

    public function review(Request $request, string $library, string $item): RedirectResponse
    {
        $this->library($library, $request); $data = $request->validate(['status' => ['required', Rule::in(['published', 'rejected'])]]);
        $table = $library === 'sermon_series' ? 'sermon_series' : 'media_assets';
        $query = DB::table($table)->where('id', $item); if ($table === 'media_assets') $query->where('library', $library);
        abort_unless($query->where('status', 'draft')->exists(), 404);
        $changes = ['status' => $data['status'], 'updated_at' => now()]; if ($table === 'media_assets') $changes['published_at'] = $data['status'] === 'published' ? now() : null;
        $query->update($changes); $this->audit($request, 'library.item_reviewed', $table, $item, ['library' => $library, 'status' => $data['status']]);
        return $this->back($library, $data['status'] === 'published' ? 'Content published.' : 'Content rejected.');
    }

    public function destroy(Request $request, string $library, string $item): RedirectResponse
    {
        $this->library($library, $request); $table = $library === 'sermon_series' ? 'sermon_series' : 'media_assets';
        $query = DB::table($table)->where('id', $item); if ($table === 'media_assets') $query->where('library', $library);
        $record = $query->first(); abort_unless($record, 404);
        DB::transaction(function () use ($request, $query, $record, $table, $item): void { if ($table === 'media_assets' && $record->file_disk && $record->file_path) Storage::disk($record->file_disk)->delete($record->file_path); if ($record->cover_image_path) Storage::disk('local')->delete($record->cover_image_path); $query->delete(); $this->audit($request, 'library.item_deleted', $table, $item); });
        return $this->back($library, 'Content removed.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->merge(['category_name' => trim((string) $request->input('category_name'))]);
        $data = $request->validateWithBag('category', ['category_name' => ['required', 'string', 'max:100', Rule::unique('library_categories', 'name'), function (string $attribute, mixed $value, \Closure $fail): void { if (DB::table('library_categories')->where('slug', Str::slug((string) $value))->exists()) $fail('A category with this name already exists.'); }], 'library' => ['required', Rule::in(self::LIBRARIES)]]);
        $data['name'] = trim($data['category_name']); unset($data['category_name']); $id = (string) Str::uuid();
        DB::table('library_categories')->insert(['id' => $id, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'library' => $data['library'] ?? null, 'created_at' => now(), 'updated_at' => now()]); $this->audit($request, 'library.category_created', 'library_categories', $id);
        return $this->back($data['library'] ?? 'books_library', 'Category created.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $request->merge(['tag_name' => trim((string) $request->input('tag_name'))]);
        $data = $request->validateWithBag('tag', ['tag_name' => ['required', 'string', 'max:80', Rule::unique('library_tags', 'name'), function (string $attribute, mixed $value, \Closure $fail): void { if (DB::table('library_tags')->where('slug', Str::slug((string) $value))->exists()) $fail('A tag with this name already exists.'); }], 'library' => ['required', Rule::in(self::LIBRARIES)]]);
        $data['name'] = trim($data['tag_name']); unset($data['tag_name']); $id = (string) Str::uuid();
        DB::table('library_tags')->insert(['id' => $id, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'created_at' => now(), 'updated_at' => now()]); $this->audit($request, 'library.tag_created', 'library_tags', $id);
        return $this->back($data['library'], 'Tag created.');
    }

    private function storeSeries(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'], 'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'cover_image_alt' => ['nullable', 'string', 'max:180']]); $id = (string) Str::uuid();
        $data['cover_image_path'] = $request->file('cover_image')?->store('library/covers', 'local'); unset($data['cover_image']);
        DB::table('sermon_series')->insert(['id' => $id, ...$data, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]); $this->audit($request, 'library.series_created', 'sermon_series', $id);
        return $this->back('sermon_series', 'Sermon series created as a draft.');
    }

    private function updateSeries(Request $request, string $item): RedirectResponse
    {
        $record = DB::table('sermon_series')->where('id', $item)->first(); abort_unless($record, 404); $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'], 'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'cover_image_alt' => ['nullable', 'string', 'max:180']]);
        $coverPath = $request->file('cover_image')?->store('library/covers', 'local'); unset($data['cover_image']); if ($coverPath) $data['cover_image_path'] = $coverPath;
        DB::table('sermon_series')->where('id', $item)->update([...$data, 'updated_at' => now()]); $this->audit($request, 'library.series_updated', 'sermon_series', $item);
        if ($coverPath && $record->cover_image_path) Storage::disk('local')->delete($record->cover_image_path);
        return $this->back('sermon_series', 'Series details saved.');
    }

    private function assetRules(string $library, bool $updating = false): array
    {
        $rules = ['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'], 'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'cover_image_alt' => ['nullable', 'string', 'max:180'], 'library_category_id' => ['nullable', 'uuid', Rule::exists('library_categories', 'id')->where(fn ($query) => $query->where(fn ($scope) => $scope->whereNull('library')->orWhere('library', $library)))], 'tag_ids' => [$updating ? 'sometimes' : 'nullable', 'array'], 'tag_ids.*' => ['uuid', 'exists:library_tags,id']];
        if ($library === 'books_library') $rules += ['author' => ['nullable', 'string', 'max:160']];
        else $rules += ['speaker' => ['nullable', 'string', 'max:160'], 'sermon_series_id' => ['nullable', 'uuid', 'exists:sermon_series,id']];
        return $rules;
    }

    private function syncTags(string $assetId, array $tags): void { DB::table('library_asset_tags')->where('media_asset_id', $assetId)->delete(); foreach (array_unique($tags) as $tag) DB::table('library_asset_tags')->insert(['media_asset_id' => $assetId, 'library_tag_id' => $tag]); }
    private function audit(Request $request, string $action, string $type, string $id, array $changes = []): void { DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]); }
    private function back(string $library, string $message): RedirectResponse { return to_route('staff.content', ['library' => $library])->with('status', $message); }
    private function library(string $library, Request $request): void
    {
        abort_unless(in_array($library, self::LIBRARIES, true), 404);
        abort_unless(app(ModuleAvailability::class)->visibleToStaff($library, $request->user()), 404);
    }
    private function youtubeId(string $url): string { $parts = parse_url($url); $host = strtolower($parts['host'] ?? ''); parse_str($parts['query'] ?? '', $query); $id = $host === 'youtu.be' ? ltrim($parts['path'] ?? '', '/') : ($query['v'] ?? (preg_match('#/(?:embed|shorts)/([A-Za-z0-9_-]{11})#', $parts['path'] ?? '', $matches) ? $matches[1] : null)); abort_unless(is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id), 422, 'Enter a valid YouTube video link.'); return $id; }
}
