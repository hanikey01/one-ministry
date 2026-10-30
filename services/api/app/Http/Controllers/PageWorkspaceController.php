<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use App\Services\PermissionChecker;
use App\Services\ModuleAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PageWorkspaceController extends Controller
{
    private const RESERVED = ['admin', 'api', 'events', 'groups', 'give', 'login', 'member', 'pages', 'resources', 'setup', 'staff', 'worship', 'books', 'sermons', 'media', 'dashboard-preview'];

    private const STARTERS = [
        'homepage' => ['label' => 'Homepage', 'slug' => 'home', 'summary' => 'A polished ministry homepage with welcome, gatherings, connection, and next steps.'],
        'about' => ['label' => 'About', 'slug' => 'about-us', 'summary' => 'Tell the ministry story, explain its mission, and introduce its values.'],
        'visit' => ['label' => 'Plan a visit', 'slug' => 'visit', 'summary' => 'Help newcomers find a gathering and know what to expect.'],
        'beliefs' => ['label' => 'Beliefs', 'slug' => 'beliefs', 'summary' => 'Present convictions in an approachable, readable structure.'],
        'leadership' => ['label' => 'Leadership', 'slug' => 'leadership', 'summary' => 'Introduce ministry leaders with flexible profile cards.'],
        'contact' => ['label' => 'Contact', 'slug' => 'contact', 'summary' => 'Combine contact guidance, directions, and a clear response path.'],
        'blank' => ['label' => 'Blank canvas', 'slug' => '', 'summary' => 'Begin with one clean section and build a custom layout.'],
    ];

    public function index(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $pages = DB::table('pages')->orderByDesc('updated_at')->get();

        return view('staff.pages.index', compact('settings', 'pages') + ['user' => $request->user(), 'starters' => self::STARTERS]);
    }

    public function create(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $starter = self::STARTERS[$request->string('template')->toString()] ?? self::STARTERS['blank'];

        return view('staff.pages.form', compact('settings', 'starter') + ['user' => $request->user(), 'page' => null, 'canPublish' => app(PermissionChecker::class)->allows($request->user(), 'content.publish'), 'blocks' => $this->starterDocument($request->string('template')->toString()), 'features' => $this->enabledFeatures(), 'aiDraftAvailable' => app(ModuleAvailability::class)->visibleToStaff('ai_page_editor', $request->user()), 'aiDraftRoute' => DB::table('ai_task_routes')->where('task_key', 'page_editor')->first()]);
    }

    public function preview(Request $request): View
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'blocks' => ['required', 'string', 'max:100000'],
        ]);
        $document = $this->document($input['blocks'], null);
        $blocks = app(\App\Services\PageDocumentService::class)->flatten($document, $this->enabledFeatures());
        $page = (object) [
            'title' => $input['title'],
            'seo_title' => $input['title'],
            'blocks' => json_encode($document, JSON_THROW_ON_ERROR),
            'body' => $this->plainTextFromBlocks($blocks),
        ];
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $features = $this->enabledFeatures();

        return view('public.page', compact('page', 'settings', 'features') + ['editorPreview' => true]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->data($request);
        $publish = $request->boolean('publish');
        if ($publish) {
            abort_unless(app(PermissionChecker::class)->allows($request->user(), 'content.publish'), 403);
            abort_if($request->user()->email_verified_at === null, 403, 'Verify your email address before publishing.');
        }
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $data, $id, $publish): void {
            DB::table('pages')->insert([...$data, 'id' => $id, 'status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->revision($id, 1, $data, $request->user()->id);
            $this->audit($request, 'content.page_created', $id, ['slug' => $data['slug'], 'status' => $publish ? 'published' : 'draft']);
            if ($publish) DB::table('pages')->where('id', $id)->update(['status' => 'published', 'published_at' => now(), 'published_by' => $request->user()->id]);
        });

        $message = $publish ? 'Page published. It is now available on the public site.' : 'Draft created. Review and publish it when it is ready.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'redirect' => route('staff.pages.edit', $id),
                    'page_id' => $id,
                    'document' => json_decode($data['blocks'], true, 512, JSON_THROW_ON_ERROR),
                ],
            ], 201);
        }

        return to_route('staff.pages.edit', $id)->with('status', $message);
    }

    public function edit(Request $request, string $page): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $page = DB::table('pages')->where('id', $page)->firstOrFail();
        $revisions = DB::table('content_revisions')->where('page_id', $page->id)->orderByDesc('revision')->get(['revision', 'created_at']);
        $canPublish = app(PermissionChecker::class)->allows($request->user(), 'content.publish');

        return view('staff.pages.form', compact('settings', 'page', 'revisions', 'canPublish') + ['user' => $request->user(), 'starter' => null, 'blocks' => $this->editorDocument($page->blocks ?? null, $page->body), 'features' => $this->enabledFeatures(), 'aiDraftAvailable' => app(ModuleAvailability::class)->visibleToStaff('ai_page_editor', $request->user()), 'aiDraftRoute' => DB::table('ai_task_routes')->where('task_key', 'page_editor')->first()]);
    }

    public function mediaCatalog(): JsonResponse
    {
        return response()->json(['assets' => $this->pageMediaAssets()->map(fn (object $asset): array => [
            'url' => $asset->provider_key,
            'label' => $asset->title,
            'type' => $asset->media_type,
        ])->values()]);
    }

    public function update(Request $request, string $page): RedirectResponse|JsonResponse
    {
        $record = DB::table('pages')->where('id', $page)->firstOrFail();
        $publish = $request->boolean('publish');
        if ($publish) {
            abort_unless(app(PermissionChecker::class)->allows($request->user(), 'content.publish'), 403);
            abort_if($request->user()->email_verified_at === null, 403, 'Verify your email address before publishing.');
        }
        $data = $this->data($request, $page);
        $migrationRevision = $this->requiresDocumentMigration($record->blocks ?? null);
        $revision = (int) DB::table('content_revisions')->where('page_id', $page)->max('revision') + ($migrationRevision ? 2 : 1);
        DB::transaction(function () use ($request, $page, $data, $revision, $record, $migrationRevision, $publish): void {
            if ($migrationRevision) $this->revision($page, $revision - 1, $this->legacyRevisionPayload($record), $request->user()->id);
            DB::table('pages')->where('id', $page)->update([...$data, 'status' => $publish ? 'published' : 'draft', 'review_notes' => null, 'published_at' => $publish ? now() : null, 'published_by' => $publish ? $request->user()->id : null, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            $this->revision($page, $revision, $data, $request->user()->id);
            $this->audit($request, $publish ? 'content.page_published' : 'content.page_updated', $page, ['revision' => $revision, 'previous_status' => $record->status, 'status' => $publish ? 'published' : 'draft']);
        });

        $message = $publish ? 'Page saved and published.' : 'Changes saved as a draft. The page needs review before it is public.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'page_id' => $page,
                    'revision' => $revision,
                    'status' => $publish ? 'published' : 'draft',
                    'document' => json_decode($data['blocks'], true, 512, JSON_THROW_ON_ERROR),
                ],
            ]);
        }

        return back()->with('status', $message);
    }

    public function quickUpdate(Request $request, string $page): RedirectResponse|JsonResponse
    {
        $record = DB::table('pages')->where('id', $page)->firstOrFail();
        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('pages', 'slug')->ignore($page)],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        abort_if(in_array($data['slug'], self::RESERVED, true), 422, 'This page address is reserved.');
        if ($data['status'] === 'published' && $record->status !== 'published') {
            abort_unless(app(PermissionChecker::class)->allows($request->user(), 'content.publish'), 403);
            abort_if($request->user()->email_verified_at === null, 403, 'Verify your email address before publishing.');
        }

        $changes = [
            ...$data,
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ];
        if ($data['status'] === 'published' && $record->status !== 'published') {
            $changes['published_at'] = now();
            $changes['published_by'] = $request->user()->id;
            $changes['review_notes'] = null;
        } elseif ($data['status'] === 'draft' && $record->status === 'published') {
            $changes['published_at'] = null;
            $changes['published_by'] = null;
        }
        DB::table('pages')->where('id', $page)->update($changes);
        $this->audit($request, 'content.page_metadata_updated', $page, [
            'previous_title' => $record->title,
            'previous_slug' => $record->slug,
            'previous_status' => $record->status,
            'status' => $data['status'],
        ]);

        $message = $data['status'] === 'published' ? 'Page published.' : 'Page saved as draft.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => ['page_id' => $page]]);
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, string $page): RedirectResponse
    {
        $record = DB::table('pages')->where('id', $page)->firstOrFail();
        DB::transaction(function () use ($request, $page, $record): void {
            DB::table('pages')->where('id', $page)->delete();
            $this->audit($request, 'content.page_deleted', $page, [
                'title' => $record->title,
                'slug' => $record->slug,
                'status' => $record->status,
            ]);
        });

        return to_route('staff.pages')->with('status', 'Page deleted.');
    }

    public function publish(Request $request, string $page): RedirectResponse
    {
        $record = DB::table('pages')->where('id', $page)->first();
        abort_unless($record, 404);
        $blocks = $this->normaliseBlocks($record->blocks ?? null, $record->body ?? null);
        foreach ($blocks as $block) {
            $data = $block['data'];
        }
        DB::transaction(function () use ($request, $page): void {
            DB::table('pages')->where('id', $page)->update(['status' => 'published', 'review_notes' => null, 'published_at' => now(), 'published_by' => $request->user()->id, 'updated_at' => now()]);
            $this->audit($request, 'content.page_reviewed', $page, ['status' => 'published']);
        });

        return redirect()->route('staff.pages.edit', $page)->with('status', 'Page published. It is now available on the public site.');
    }

    private function data(Request $request, ?string $page = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('pages', 'slug')->ignore($page)],
            'body' => ['nullable', 'string', 'max:50000'],
            'blocks' => ['nullable', 'string', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ]);
        abort_if(in_array($data['slug'], self::RESERVED, true), 422, 'This page address is reserved.');

        $document = $this->document($data['blocks'] ?? null, $data['body'] ?? null);
        $blocks = app(\App\Services\PageDocumentService::class)->flatten($document, $this->enabledFeatures());
        $data['blocks'] = json_encode($document, JSON_THROW_ON_ERROR);
        $data['body'] = $this->plainTextFromBlocks($blocks);

        return $data;
    }

    /** @return array<int, array{type: string, data: array<string, string>}> */
    private function editorBlocks(?string $encodedBlocks, ?string $body): array
    {
        if ($encodedBlocks) {
            try {
                $decoded = json_decode($encodedBlocks, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    if (($decoded['version'] ?? null) === 2) {
                        return app(\App\Services\PageDocumentService::class)->flatten($decoded, $this->enabledFeatures());
                    }
                    return $decoded;
                }
            } catch (\JsonException) {
                // Legacy content continues to open in the editor as readable text blocks.
            }
        }

        return $this->normaliseBlocks(null, $body);
    }

    /** Return version 2 content to the structured canvas without flattening saved hierarchy. */
    private function editorDocument(?string $encodedBlocks, ?string $body): array
    {
        $service = app(\App\Services\PageDocumentService::class);
        if ($encodedBlocks) {
            try {
                $decoded = json_decode($encodedBlocks, true, 512, JSON_THROW_ON_ERROR);
                if ($service->isStructured($decoded)) return $service->normalise($decoded, $this->enabledFeatures());
            } catch (\JsonException) {
                // Recover legacy text content through the normal editor boundary.
            }
        }

        return $service->fromFlat($this->editorBlocks($encodedBlocks, $body), $this->enabledFeatures());
    }

    /** @return array<int, array{type: string, data: array<string, string>}> */
    private function normaliseBlocks(?string $encodedBlocks, ?string $legacyBody): array
    {
        if (! $encodedBlocks) {
            $paragraphs = array_values(array_filter(preg_split('/\R\s*\R/', (string) $legacyBody) ?: [], fn (string $part): bool => trim($part) !== ''));

            return $paragraphs === []
                ? [['type' => 'paragraph', 'data' => ['text' => 'Start writing your page here.']]]
                : array_map(fn (string $text): array => ['type' => 'paragraph', 'data' => ['text' => trim($text)]], $paragraphs);
        }

        try {
            $decoded = json_decode($encodedBlocks, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['blocks' => 'The page layout could not be read. Refresh the editor and try again.']);
        }

        if (! is_array($decoded) || count($decoded) > 60) {
            throw ValidationException::withMessages(['blocks' => 'A page can contain up to 60 blocks.']);
        }

        if (($decoded['version'] ?? null) === 2) {
            return app(\App\Services\PageDocumentService::class)->flatten($decoded, $this->enabledFeatures());
        }

        $allowed = [
            'hero' => ['eyebrow', 'title', 'body', 'button_label', 'button_url'],
            'heading' => ['text'],
            'paragraph' => ['text'],
            'image' => ['url', 'alt', 'caption', 'image_hint'],
            'gallery' => ['images', 'columns', 'caption'],
            'video_embed' => ['url', 'title', 'caption'],
            'audio' => ['url', 'title', 'caption'],
            'file' => ['url', 'label', 'description'],
            'media_text' => ['media_url', 'media_alt', 'placement', 'title', 'body', 'button_label', 'button_url'],
            'list' => ['title', 'items', 'ordered'],
            'accordion' => ['title', 'question_one', 'answer_one', 'question_two', 'answer_two', 'question_three', 'answer_three'],
            'spacer' => ['height'],
            'call_to_action' => ['title', 'body', 'button_label', 'button_url'],
            'quote' => ['text', 'attribution'],
            'service_times' => ['title', 'body'],
            'events' => ['title', 'body'],
            'groups' => ['title', 'body'],
            'carousel' => ['eyebrow', 'title', 'body', 'slides', 'button_label', 'button_url'],
            'live_service' => ['title', 'body', 'button_label', 'button_url'],
            'locations' => ['title', 'body', 'button_label', 'button_url'],
            'giving' => ['title', 'body', 'button_label', 'button_url'],
            'prayer' => ['title', 'body', 'button_label', 'button_url'],
            'sermon_spotlight' => ['title', 'body', 'button_label', 'button_url'],
            'books' => ['title', 'body', 'button_label', 'button_url'],
            'announcements' => ['title', 'body', 'button_label', 'button_url'],
            'volunteer' => ['title', 'body', 'button_label', 'button_url'],
            'newsletter' => ['title', 'body', 'button_label', 'button_url'],
            'next_steps' => ['title', 'body', 'button_label', 'button_url'],
            'welcome' => ['title', 'body', 'button_label', 'button_url'],
            'custom' => ['title', 'body', 'button_label', 'button_url'],
            'section' => ['layout', 'title', 'body', 'column_one_title', 'column_one_body', 'column_two_title', 'column_two_body', 'column_three_title', 'column_three_body', 'background_color', 'padding'],
            'divider' => [],
        ];
        if (! in_array('groups', $this->enabledFeatures(), true)) {
            unset($allowed['groups']);
        }
        foreach (['live_service' => 'digital_church', 'giving' => 'giving', 'books' => 'books_library', 'volunteer' => 'volunteers'] as $blockType => $feature) {
            if (! in_array($feature, $this->enabledFeatures(), true)) {
                unset($allowed[$blockType]);
            }
        }

        $blocks = [];
        foreach ($decoded as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || ! array_key_exists($block['type'], $allowed)) {
                throw ValidationException::withMessages(['blocks' => 'The page contains an unsupported block.']);
            }
            $source = is_array($block['data'] ?? null) ? $block['data'] : [];
            $data = [];
            foreach ($allowed[$block['type']] as $key) {
                $value = $source[$key] ?? '';
                if (! is_scalar($value)) {
                    throw ValidationException::withMessages(['blocks' => 'Block fields must contain plain text.']);
                }
                $data[$key] = Str::limit(trim((string) $value), $key === 'body' || $key === 'text' ? 12000 : 500);
                if ($key === 'background_color' && $data[$key] !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $data[$key])) throw ValidationException::withMessages(['blocks' => 'Section background colours must use a six-digit hex value.']);
                if ($key === 'layout' && ! in_array($data[$key], ['one', 'two', 'three'], true)) throw ValidationException::withMessages(['blocks' => 'Choose a valid section column layout.']);
                if ($key === 'padding' && ! in_array($data[$key], ['compact', 'regular', 'spacious'], true)) throw ValidationException::withMessages(['blocks' => 'Choose a valid section spacing option.']);
                if (in_array($key, ['url', 'media_url'], true) && $data[$key] !== '' && ! preg_match('#^https?://#i', $data[$key])) throw ValidationException::withMessages(['blocks' => 'Media links must use an HTTP or HTTPS URL.']);
                if ($key === 'images' && $data[$key] !== '') {
                    $images = array_values(array_filter(preg_split('/\R/', $data[$key]) ?: []));
                    if (count($images) > 12 || collect($images)->contains(fn (string $url): bool => ! preg_match('#^https?://#i', trim($url)))) throw ValidationException::withMessages(['blocks' => 'A gallery accepts up to 12 HTTP or HTTPS image links.']);
                }
            }
            if ($block['type'] === 'video_embed' && $data['url'] !== '' && ! $this->isSupportedVideoUrl($data['url'])) throw ValidationException::withMessages(['blocks' => 'Video embeds currently support YouTube and Vimeo links only.']);
            if ($block['type'] === 'gallery' && ! in_array($data['columns'], ['two', 'three', 'four'], true)) throw ValidationException::withMessages(['blocks' => 'Choose a valid gallery column layout.']);
            if ($block['type'] === 'media_text' && ! in_array($data['placement'], ['left', 'right'], true)) throw ValidationException::withMessages(['blocks' => 'Choose a valid media placement.']);
            if ($block['type'] === 'list' && ! in_array($data['ordered'], ['yes', 'no'], true)) throw ValidationException::withMessages(['blocks' => 'Choose a valid list style.']);
            if ($block['type'] === 'spacer' && ! in_array($data['height'], ['small', 'medium', 'large'], true)) throw ValidationException::withMessages(['blocks' => 'Choose a valid spacer height.']);
            $blocks[] = ['type' => $block['type'], 'data' => $data];
        }

        return $blocks === [] ? [['type' => 'paragraph', 'data' => ['text' => 'Start writing your page here.']]] : $blocks;
    }

    /** Preserve an incoming v2 hierarchy; legacy drafts convert only at this boundary. */
    private function document(?string $encodedBlocks, ?string $legacyBody): array
    {
        $service = app(\App\Services\PageDocumentService::class);
        if ($encodedBlocks) {
            try {
                $decoded = json_decode($encodedBlocks, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['blocks' => 'The page layout could not be read. Refresh the editor and try again.']);
            }
            if ($service->isStructured($decoded)) return $service->normalise($decoded, $this->enabledFeatures());
        }
        return $service->fromFlat($this->normaliseBlocks($encodedBlocks, $legacyBody), $this->enabledFeatures());
    }

    private function requiresDocumentMigration(?string $encodedBlocks): bool
    {
        if (! $encodedBlocks) return true;
        try { return ! app(\App\Services\PageDocumentService::class)->isStructured(json_decode($encodedBlocks, true, 512, JSON_THROW_ON_ERROR)); } catch (\JsonException) { return true; }
    }

    private function legacyRevisionPayload(object $page): array
    {
        return ['title' => $page->title, 'slug' => $page->slug, 'body' => $page->body, 'blocks' => $page->blocks, 'seo_title' => $page->seo_title, 'seo_description' => $page->seo_description, 'migration_snapshot' => 'legacy_flat_document'];
    }

    private function isSupportedVideoUrl(string $url): bool
    {
        return preg_match('#^https?://(?:(?:www\.)?(?:youtube\.com|youtu\.be)|(?:www\.)?vimeo\.com)/#i', $url) === 1;
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function pageMediaAssets(): \Illuminate\Support\Collection
    {
        return DB::table('media_assets')
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get(['id', 'title', 'provider_key', 'provider', 'library', 'kind', 'file_mime'])
            ->map(function (object $asset): object {
                if (($asset->provider ?? 'local') === 'local' && ! str_starts_with((string) $asset->provider_key, 'http')) {
                    $asset->provider_key = route('public.media', $asset->id);
                }
                $mime = strtolower((string) ($asset->file_mime ?? ''));
                $kind = strtolower((string) ($asset->kind ?? ''));
                $asset->media_type = str_starts_with($mime, 'image/') || $kind === 'image' ? 'image'
                    : (str_starts_with($mime, 'video/') || $kind === 'video' ? 'video'
                    : (str_starts_with($mime, 'audio/') || $kind === 'audio' ? 'audio' : 'file'));
                return $asset;
            });
    }

    /** @return array<int, string> */
    private function enabledFeatures(): array
    {
        return FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();
    }

    /** @return array{version:int,sections:array<int,array<string,mixed>>} */
    private function starterDocument(string $template): array
    {
        $hero = fn (string $eyebrow, string $title, string $body, string $label = '', string $url = '') => $this->starterElement('hero', compact('eyebrow', 'title', 'body') + ['button_label' => $label, 'button_url' => $url], [
            'text_color' => '#ffffff', 'button_background' => 'surface', 'button_hover_background' => 'surface_muted', 'button_text_color' => '#17324d', 'button_radius' => '999px', 'button_padding_x' => '28px', 'button_padding_y' => '14px',
            'custom_css' => 'selector .public-page-hero { min-height: min(680px, 78vh); border-radius: 28px; padding: clamp(44px, 8vw, 108px); background: radial-gradient(circle at 88% 12%, rgb(255 255 255 / 16%), transparent 31%), linear-gradient(135deg, #102a43 0%, #173f5f 58%, #2f6f73 100%); box-shadow: 0 28px 70px rgb(16 42 67 / 24%); } selector .public-page-hero small { width: max-content; border: 1px solid rgb(255 255 255 / 28%); border-radius: 999px; padding: 7px 12px; background: rgb(255 255 255 / 10%); font-size: .72rem; font-weight: 800; letter-spacing: .16em; } selector .public-page-hero h1 { max-width: 14ch; font-family: Georgia, serif; font-size: clamp(2.6rem, 7vw, 5.6rem); line-height: .98; letter-spacing: -.055em; } selector .public-page-hero p { max-width: 58ch; font-size: clamp(1rem, 2vw, 1.2rem); line-height: 1.75; opacity: .88; } selector .public-page-hero .button { margin-top: 10px; font-weight: 800; }',
        ], ['desktop' => ['padding' => ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px']], 'mobile' => ['padding' => ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px']]]);
        $feature = fn (string $icon, string $title, string $body) => $this->starterElement('icon_box', ['icon' => $icon, 'title' => $title, 'body' => $body, 'button_label' => '', 'button_url' => ''], [
            'background_color' => 'surface', 'radius_top_left' => '22px', 'radius_top_right' => '22px', 'radius_bottom_right' => '22px', 'radius_bottom_left' => '22px', 'shadow_x' => '0px', 'shadow_y' => '14px', 'shadow_blur' => '42px', 'shadow_spread' => '0px', 'shadow_color' => '#000000',
            'custom_css' => 'selector .public-page-icon-box { height: 100%; box-sizing: border-box; border: 1px solid rgb(23 50 77 / 9%); border-radius: 22px; padding: clamp(26px, 3vw, 38px); box-shadow: 0 16px 44px rgb(16 42 67 / 9%); } selector .public-page-icon-box .ui-icon { box-sizing: content-box; border-radius: 15px; padding: 12px; background: #e9f3ef; color: #2f6f73; } selector .public-page-icon-box h2 { margin-top: 10px; font-family: Georgia, serif; font-size: clamp(1.35rem, 2vw, 1.75rem); letter-spacing: -.025em; } selector .public-page-icon-box p { font-size: .98rem; line-height: 1.75; }',
        ], ['desktop' => ['padding' => ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px']]]);
        $leadSettings = ['content_width' => 'wide', 'min_height' => '72vh', 'vertical_alignment' => 'center', 'background_color' => 'action', 'padding' => 'spacious', 'overflow' => 'hidden'];
        $standardSettings = ['content_width' => 'standard', 'padding' => 'spacious', 'column_gap' => 'regular'];
        $compactSettings = ['content_width' => 'standard', 'padding' => 'regular', 'column_gap' => 'regular'];

        $documents = [
            'homepage' => [
                $this->starterSection([$this->starterColumn([$hero('WELCOME HOME', 'Faith grows best in community.', 'A warm, confident introduction to your ministry—created to help every visitor feel seen, find a gathering, and take a meaningful next step.', 'Plan your visit', '/visit')])], [...$leadSettings, 'background_color' => '#f4f0e8'], ['desktop' => ['padding' => ['top' => '34px', 'right' => '34px', 'bottom' => '34px', 'left' => '34px']], 'mobile' => ['padding' => ['top' => '12px', 'right' => '12px', 'bottom' => '12px', 'left' => '12px']]]),
                $this->starterSection([
                    $this->starterColumn([$feature('people', 'Find your people', 'Discover relationships, small groups, and ministry spaces where you can belong and grow.')]),
                    $this->starterColumn([$feature('calendar', 'Join us this week', 'Choose a gathering and arrive with everything you need for a relaxed first visit.')]),
                    $this->starterColumn([$feature('hand-heart', 'Take your next step', 'Explore simple ways to connect, serve, ask for prayer, or learn more about faith.')]),
                ], $standardSettings + ['title' => 'There is a place for you here', 'body' => 'Three clear paths help newcomers move from browsing to belonging.', 'background_color' => '#f7f9fb', 'custom_css' => 'selector .public-page-structured-heading { margin-bottom: 18px; } selector .public-page-structured-heading h2 { max-width: 15ch; font-family: Georgia, serif; font-size: clamp(2rem, 4vw, 3.4rem); color: #102a43; } selector .public-page-structured-heading p { max-width: 54ch; font-size: 1.05rem; }'], ['tablet' => ['column_count' => '2'], 'mobile' => ['stack' => 'stack']]),
                $this->starterSection([
                    $this->starterColumn([
                        $this->starterElement('rich_text', ['title' => 'More than a service. A community for everyday life.', 'text' => "Use this finished section to introduce the heart of your ministry. Share what people can expect: thoughtful worship, practical teaching, genuine relationships, and room to grow at their own pace.\n\nReplace this copy with your ministry’s real voice before publishing."], ['font_family' => 'georgia', 'text_color' => '#102a43', 'custom_css' => 'selector .public-page-rich-text h2 { max-width: 15ch; font-size: clamp(2rem, 4vw, 3.6rem); line-height: 1.06; letter-spacing: -.045em; } selector .public-page-rich-text p { max-width: 60ch; font-family: system-ui, sans-serif; font-size: 1rem; line-height: 1.85; color: #425466; }'], ['desktop' => ['padding' => ['top' => '10px', 'right' => '20px', 'bottom' => '10px', 'left' => '0px']]])
                    ], ['vertical_alignment' => 'center']),
                    $this->starterColumn([
                        $this->starterElement('statistics', ['title' => 'Life together', 'stat_one_value' => '01', 'stat_one_label' => 'Gather in worship', 'stat_two_value' => '02', 'stat_two_label' => 'Grow in community', 'stat_three_value' => '03', 'stat_three_label' => 'Serve with purpose'], ['background_color' => '#173f5f', 'text_color' => '#ffffff', 'radius_top_left' => '26px', 'radius_top_right' => '26px', 'radius_bottom_right' => '26px', 'radius_bottom_left' => '26px', 'custom_css' => 'selector .public-page-statistics { border: 0; border-radius: 26px; padding: clamp(30px, 5vw, 58px); background: linear-gradient(145deg, #173f5f, #102a43); color: white; box-shadow: 0 24px 54px rgb(16 42 67 / 22%); } selector .public-page-statistics h2 { color: #b9ddd2; letter-spacing: .1em; text-transform: uppercase; font-size: .78rem; } selector .public-page-statistics article { border-color: rgb(255 255 255 / 16%); } selector .public-page-statistics strong { color: white; font-family: Georgia, serif; } selector .public-page-statistics span { color: rgb(255 255 255 / 72%); }'], ['desktop' => ['padding' => ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px']]])
                    ], ['vertical_alignment' => 'center']),
                ], $standardSettings + ['background_color' => '#ffffff', 'column_gap' => 'spacious'], ['mobile' => ['stack' => 'stack']]),
                $this->starterSection([
                    $this->starterColumn([$this->starterElement('service_times', ['title' => 'Gather with us', 'body' => 'Current public service times and locations appear here automatically.'], ['custom_css' => 'selector .public-page-dynamic-list { height: 100%; box-sizing: border-box; border: 0; border-radius: 24px; padding: clamp(28px, 4vw, 44px); background: #ffffff; box-shadow: 0 16px 44px rgb(16 42 67 / 9%); } selector .public-page-dynamic-list small { color: #2f6f73; }'])]),
                    $this->starterColumn([$this->starterElement('events', ['title' => 'Something to look forward to', 'body' => 'Published upcoming events appear here automatically.'], ['custom_css' => 'selector .public-page-dynamic-list { height: 100%; box-sizing: border-box; border: 0; border-radius: 24px; padding: clamp(28px, 4vw, 44px); background: #173f5f; color: #ffffff; box-shadow: 0 16px 44px rgb(16 42 67 / 18%); } selector .public-page-dynamic-list :is(h2, p, small, strong, span, a) { color: inherit; } selector .public-page-dynamic-list p, selector .public-page-dynamic-list span { opacity: .76; }'])]),
                ], $standardSettings + ['title' => 'This week at the ministry', 'body' => 'Live ministry information keeps the homepage useful without manual duplication.', 'background_color' => '#edf4f2', 'custom_css' => 'selector .public-page-structured-heading h2 { font-family: Georgia, serif; font-size: clamp(2rem, 4vw, 3.2rem); color: #102a43; }'], ['mobile' => ['stack' => 'stack']]),
                $this->starterSection([
                    $this->starterColumn([$this->starterElement('welcome', ['title' => 'Come as you are', 'body' => 'You do not need to have everything figured out. Begin with a visit and meet people who will be glad to welcome you.'], ['custom_css' => 'selector .public-page-callout { height: 100%; box-sizing: border-box; border: 1px solid #d7e4df; border-radius: 24px; padding: clamp(28px, 4vw, 46px); background: #f4f8f6; } selector .public-page-callout h2 { font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); color: #102a43; }'])]),
                    $this->starterColumn([$this->starterElement('next_steps', ['title' => 'Ready for what is next?', 'body' => 'Connect with a team, join a group, discover faith, or find a place to serve.'], ['custom_css' => 'selector .public-page-callout { height: 100%; box-sizing: border-box; border: 0; border-radius: 24px; padding: clamp(28px, 4vw, 46px); background: #f4f0e8; } selector .public-page-callout h2 { font-family: Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.6rem); color: #102a43; }'])]),
                ], $standardSettings + ['title' => 'Start where you are', 'background_color' => '#ffffff', 'custom_css' => 'selector .public-page-structured-heading h2 { font-family: Georgia, serif; font-size: clamp(2rem, 4vw, 3.2rem); color: #102a43; }'], ['mobile' => ['stack' => 'stack']]),
                $this->starterSection([$this->starterColumn([$this->starterElement('call_to_action', ['title' => 'Your story can begin here.', 'body' => 'Join us this week and discover a community where faith, hope, and everyday life come together.', 'button_label' => 'Plan your visit', 'button_url' => '/visit'], ['button_background' => 'surface', 'button_hover_background' => 'surface_muted', 'button_text_color' => '#17324d', 'button_radius' => '999px', 'button_padding_x' => '28px', 'button_padding_y' => '14px', 'custom_css' => 'selector .public-page-cta { min-height: 360px; align-content: center; border-radius: 28px; padding: clamp(38px, 7vw, 86px); background: radial-gradient(circle at 90% 10%, rgb(255 255 255 / 18%), transparent 30%), linear-gradient(135deg, #2f6f73, #173f5f); box-shadow: 0 26px 64px rgb(16 42 67 / 20%); } selector .public-page-cta h2 { max-width: 14ch; font-family: Georgia, serif; font-size: clamp(2.2rem, 5vw, 4.4rem); line-height: 1; } selector .public-page-cta p { font-size: 1.05rem; }'])])], $compactSettings + ['background_color' => '#f4f0e8'], ['desktop' => ['padding' => ['top' => '64px', 'right' => '34px', 'bottom' => '64px', 'left' => '34px']], 'mobile' => ['padding' => ['top' => '32px', 'right' => '12px', 'bottom' => '32px', 'left' => '12px']]]),
            ],
            'about' => [
                $this->starterSection([$this->starterColumn([$hero('ABOUT', 'Tell the story behind your ministry.', 'Introduce the calling, people, and purpose that shape this community.')])], $leadSettings),
                $this->starterSection([$this->starterColumn([$this->starterElement('rich_text', ['title' => 'Our story', 'text' => 'Replace this text with an honest account of how the ministry began, who it serves, and what God has done through the community.'])])], $standardSettings),
                $this->starterSection([
                    $this->starterColumn([$feature('book', 'Our foundation', 'Summarize the convictions that guide the ministry.')]),
                    $this->starterColumn([$feature('people', 'Our community', 'Describe the people and place the ministry is called to serve.')]),
                    $this->starterColumn([$feature('globe', 'Our mission', 'Explain the impact the ministry hopes to make.')]),
                ], $standardSettings + ['title' => 'What shapes us'], ['mobile' => ['stack' => 'stack']]),
                $this->starterSection([$this->starterColumn([$this->starterElement('call_to_action', ['title' => 'Come and experience the community', 'body' => 'Invite readers to take a practical next step.', 'button_label' => 'Plan a visit', 'button_url' => '/visit'])])], $compactSettings + ['background_color' => 'surface_muted']),
            ],
            'visit' => [
                $this->starterSection([$this->starterColumn([$hero('PLAN A VISIT', 'Know what to expect before you arrive.', 'Add the practical details that help a first visit feel simple and welcoming.', 'Find a location', '/locations')])], $leadSettings),
                $this->starterSection([$this->starterColumn([$this->starterElement('service_times', ['title' => 'Choose a gathering', 'body' => 'Public service times from the ministry schedule appear here.'])])], $standardSettings),
                $this->starterSection([
                    $this->starterColumn([$feature('location', 'Arrival and parking', 'Explain where to enter, park, and ask for assistance.')]),
                    $this->starterColumn([$feature('people', 'Children and families', 'Describe check-in and age-appropriate ministry without making assumptions.')]),
                    $this->starterColumn([$feature('heart', 'Accessibility and care', 'Share accessibility details and how visitors can request help.')]),
                ], $standardSettings + ['title' => 'Your first visit'], ['mobile' => ['stack' => 'stack']]),
                $this->starterSection([$this->starterColumn([$this->starterElement('accordion', ['title' => 'Frequently asked questions', 'question_one' => 'What should I wear?', 'answer_one' => 'Add the ministry’s helpful answer.', 'question_two' => 'When should I arrive?', 'answer_two' => 'Add an arrival recommendation.', 'question_three' => 'Who can help when I arrive?', 'answer_three' => 'Explain how visitors can identify the welcome team.'])])], $standardSettings),
            ],
            'beliefs' => [
                $this->starterSection([$this->starterColumn([$hero('OUR BELIEFS', 'Explain what your ministry believes with clarity and grace.', 'Use this page to describe the convictions that shape worship, community, and service.')])], $leadSettings),
                $this->starterSection([
                    $this->starterColumn([$this->starterElement('rich_text', ['title' => 'Core belief', 'text' => 'Add a concise statement and supporting explanation.'])]),
                    $this->starterColumn([$this->starterElement('rich_text', ['title' => 'Life together', 'text' => 'Explain how belief is practiced in community.'])]),
                    $this->starterColumn([$this->starterElement('rich_text', ['title' => 'Mission and service', 'text' => 'Explain how belief shapes the ministry’s public mission.'])]),
                ], $standardSettings + ['title' => 'A foundation for faith and life'], ['mobile' => ['stack' => 'stack']]),
                $this->starterSection([$this->starterColumn([$this->starterElement('quote', ['text' => 'Add a short Scripture passage or ministry statement that expresses the heart of this page.', 'attribution' => ''])])], $compactSettings + ['background_color' => 'surface_muted']),
            ],
            'leadership' => [
                $this->starterSection([$this->starterColumn([$hero('LEADERSHIP', 'Introduce the people who serve and lead.', 'Use real names, roles, photographs, and biographies before publishing this page.')])], $leadSettings),
                $this->starterSection([
                    $this->starterColumn([$this->starterElement('image_card', ['url' => '', 'alt' => '', 'title' => 'Leader name', 'body' => 'Role and short biography.', 'button_label' => '', 'button_url' => ''])]),
                    $this->starterColumn([$this->starterElement('image_card', ['url' => '', 'alt' => '', 'title' => 'Leader name', 'body' => 'Role and short biography.', 'button_label' => '', 'button_url' => ''])]),
                    $this->starterColumn([$this->starterElement('image_card', ['url' => '', 'alt' => '', 'title' => 'Leader name', 'body' => 'Role and short biography.', 'button_label' => '', 'button_url' => ''])]),
                ], $standardSettings + ['title' => 'Meet the team'], ['tablet' => ['column_count' => '2'], 'mobile' => ['stack' => 'stack']]),
                $this->starterSection([$this->starterColumn([$this->starterElement('call_to_action', ['title' => 'Looking for a ministry team?', 'body' => 'Direct visitors to the appropriate public contact route.', 'button_label' => 'Contact the ministry', 'button_url' => '/contact'])])], $compactSettings + ['background_color' => 'surface_muted']),
            ],
            'contact' => [
                $this->starterSection([$this->starterColumn([$hero('CONTACT', 'Make it easy for people to reach the right team.', 'Explain expected response times and never request sensitive information through a public page.')])], $leadSettings),
                $this->starterSection([
                    $this->starterColumn([$this->starterElement('contact_form', ['title' => 'Send a message', 'body' => 'Add the public contact fields and consent language appropriate for your ministry.', 'submit_label' => 'Send message'])]),
                    $this->starterColumn([$this->starterElement('map_directions', ['title' => 'Visit in person', 'address' => 'Add the public ministry address.', 'directions_url' => '', 'button_label' => 'Get directions'])]),
                ], $standardSettings + ['title' => 'We would be glad to hear from you'], ['mobile' => ['stack' => 'stack']]),
            ],
            'blank' => [$this->starterSection([$this->starterColumn([])], ['content_width' => 'standard', 'padding' => 'regular'])],
        ];

        $document = ['version' => 2, 'sections' => $documents[$template] ?? $documents['blank']];

        return app(\App\Services\PageDocumentService::class)->normalise($document, $this->enabledFeatures());
    }

    /** @param array<int,array<string,mixed>> $columns */
    private function starterSection(array $columns, array $settings = [], array $responsive = []): array
    {
        $responsive['mobile'] = ($responsive['mobile'] ?? []) + ['padding' => ['top' => '36px', 'right' => '22px', 'bottom' => '36px', 'left' => '22px']];

        return ['id' => (string) Str::uuid(), 'type' => 'section', 'settings' => $settings, 'responsive' => $responsive, 'columns' => $columns];
    }

    /** @param array<int,array<string,mixed>> $elements */
    private function starterColumn(array $elements, array $settings = [], array $responsive = []): array
    {
        return ['id' => (string) Str::uuid(), 'settings' => $settings, 'responsive' => $responsive, 'elements' => $elements];
    }

    private function starterElement(string $type, array $content, array $settings = [], array $responsive = []): array
    {
        return ['id' => (string) Str::uuid(), 'type' => $type, 'content' => $content, 'settings' => $settings, 'responsive' => $responsive];
    }

    /** @param array<int, array{type: string, data: array<string, string>}> $blocks */
    private function plainTextFromBlocks(array $blocks): string
    {
        $copy = collect($blocks)->map(function (array $block): string {
            $data = $block['data'];

            return match ($block['type']) {
                'hero', 'call_to_action' => trim(($data['title'] ?? '')."\n".($data['body'] ?? '')),
                'heading', 'paragraph', 'quote' => $data['text'] ?? '',
                'image', 'gallery', 'audio', 'file' => $data['caption'] ?? $data['description'] ?? '',
                'video_embed' => $data['title'] ?? $data['caption'] ?? '',
                'media_text' => trim(($data['title'] ?? '')."\n".($data['body'] ?? '')),
                'list' => trim(($data['title'] ?? '')."\n".($data['items'] ?? '')),
                'accordion' => trim(($data['title'] ?? '')."\n".($data['question_one'] ?? '')."\n".($data['answer_one'] ?? '')."\n".($data['question_two'] ?? '')."\n".($data['answer_two'] ?? '')."\n".($data['question_three'] ?? '')."\n".($data['answer_three'] ?? '')),
                'section' => trim(($data['title'] ?? '')."\n".($data['body'] ?? '')."\n".($data['column_one_body'] ?? '')."\n".($data['column_two_body'] ?? '')."\n".($data['column_three_body'] ?? '')), 
                default => $data['body'] ?? $data['title'] ?? '',
            };
        })->filter()->implode("\n\n");

        return Str::limit($copy, 50000, '');
    }

    private function revision(string $page, int $revision, array $payload, string $user): void
    {
        DB::table('content_revisions')->insert(['id' => (string) Str::uuid(), 'page_id' => $page, 'revision' => $revision, 'payload' => json_encode($payload), 'created_by' => $user, 'created_at' => now()]);
    }

    private function audit(Request $request, string $action, string $page, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'pages', 'subject_id' => $page, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
