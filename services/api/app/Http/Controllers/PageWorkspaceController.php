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
        'about' => ['label' => 'About us', 'slug' => 'about-us', 'summary' => 'Share your story, mission, and the people you serve.', 'body' => "Our story\n\nShare how this ministry began and the community it serves.\n\nOur mission\n\nState the purpose that guides your ministry and the invitation you extend to people."],
        'visit' => ['label' => 'Plan your visit', 'slug' => 'visit', 'summary' => 'Give first-time guests the practical details they need.', 'body' => "You are welcome here\n\nTell guests what they can expect when they arrive.\n\nWhen and where\n\nAdd your service times, location, parking, and accessibility information.\n\nWhat to expect\n\nDescribe the experience in a warm, straightforward way."],
        'beliefs' => ['label' => 'What we believe', 'slug' => 'beliefs', 'summary' => 'Explain your convictions with clear, welcoming language.', 'body' => "What we believe\n\nIntroduce the convictions that shape your worship, life together, and service.\n\nOur foundation\n\nAdd the beliefs and practices you want people to understand."],
        'leadership' => ['label' => 'Leadership', 'slug' => 'leadership', 'summary' => 'Introduce pastoral staff and ministry leaders.', 'body' => "Meet our leaders\n\nIntroduce the people who serve and lead this ministry.\n\nLeadership team\n\nAdd each leader's name, role, and a short introduction."],
        'contact' => ['label' => 'Contact us', 'slug' => 'contact', 'summary' => 'Make it simple for people to reach the ministry.', 'body' => "Get in touch\n\nShare the best way for people to contact your ministry.\n\nOffice hours\n\nAdd office hours, an email address, phone number, and location details."],
        'blank' => ['label' => 'Blank page', 'slug' => '', 'summary' => 'Start with a title and write the page your way.', 'body' => ''],
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

        return view('staff.pages.form', compact('settings', 'starter') + ['user' => $request->user(), 'page' => null, 'blocks' => app(\App\Services\PageDocumentService::class)->fromFlat($this->starterBlocks($request->string('template')->toString(), $starter['body']), $this->enabledFeatures()), 'features' => $this->enabledFeatures(), 'aiDraftAvailable' => app(ModuleAvailability::class)->visibleToStaff('ai_page_editor', $request->user()), 'aiDraftRoute' => DB::table('ai_task_routes')->where('task_key', 'page_editor')->first()]);
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
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $data, $id): void {
            DB::table('pages')->insert([...$data, 'id' => $id, 'status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->revision($id, 1, $data, $request->user()->id);
            $this->audit($request, 'content.page_created', $id, ['slug' => $data['slug']]);
        });

        $message = 'Draft created. Review and publish it when it is ready.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => ['redirect' => route('staff.pages.edit', $id), 'page_id' => $id],
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
        $data = $this->data($request, $page);
        $migrationRevision = $this->requiresDocumentMigration($record->blocks ?? null);
        $revision = (int) DB::table('content_revisions')->where('page_id', $page)->max('revision') + ($migrationRevision ? 2 : 1);
        DB::transaction(function () use ($request, $page, $data, $revision, $record, $migrationRevision): void {
            if ($migrationRevision) $this->revision($page, $revision - 1, $this->legacyRevisionPayload($record), $request->user()->id);
            DB::table('pages')->where('id', $page)->update([...$data, 'status' => 'draft', 'review_notes' => null, 'published_at' => null, 'published_by' => null, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            $this->revision($page, $revision, $data, $request->user()->id);
            $this->audit($request, 'content.page_updated', $page, ['revision' => $revision, 'previous_status' => $record->status]);
        });

        $message = 'Changes saved as a draft. The page needs review before it is public.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => ['page_id' => $page, 'revision' => $revision, 'status' => 'draft'],
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

    public function publish(Request $request, string $page): RedirectResponse
    {
        $record = DB::table('pages')->where('id', $page)->where('status', 'draft')->first();
        abort_unless($record, 404);
        $blocks = $this->normaliseBlocks($record->blocks ?? null, $record->body ?? null);
        foreach ($blocks as $block) {
            $data = $block['data'];
            if (($block['type'] === 'image' && blank($data['url']))
                || ($block['type'] === 'media_text' && blank($data['media_url']))
                ) {
                throw ValidationException::withMessages(['blocks' => 'Choose an image for every image section, or remove sections you do not need.']);
            }
            if ((filled($data['button_label'] ?? '') && blank($data['button_url'] ?? ''))
                || (filled($data['label'] ?? '') && $block['type'] === 'button' && blank($data['url'] ?? ''))) {
                throw ValidationException::withMessages(['blocks' => 'Choose a destination for every button, or remove its label before publishing.']);
            }
        }
        DB::transaction(function () use ($request, $page): void {
            DB::table('pages')->where('id', $page)->where('status', 'draft')->update(['status' => 'published', 'review_notes' => null, 'published_at' => now(), 'published_by' => $request->user()->id, 'updated_at' => now()]);
            $this->audit($request, 'content.page_reviewed', $page, ['status' => 'published']);
        });

        return back()->with('status', 'Page published. It is now available on the public site.');
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
                if ($key === 'button_url' && $data[$key] !== '' && ! preg_match('#^(?:https?://|/)#i', $data[$key])) throw ValidationException::withMessages(['blocks' => 'Button destinations must use an HTTP(S) link or an internal path.']);
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

    /** @return array<int, array{type: string, data: array<string, string>}> */
    private function starterBlocks(string $template, ?string $fallback): array
    {
        $starters = [
            'about' => [
                ['type' => 'hero', 'data' => ['eyebrow' => 'WELCOME', 'title' => 'A place to belong', 'body' => 'We are a community learning to follow Jesus together.', 'button_label' => 'Plan your visit', 'button_url' => '/pages/visit']],
                ['type' => 'heading', 'data' => ['text' => 'Our story']],
                ['type' => 'paragraph', 'data' => ['text' => 'Share how this ministry began and the people it serves.']],
                ['type' => 'call_to_action', 'data' => ['title' => 'Come and see', 'body' => 'We would be glad to welcome you this week.', 'button_label' => 'Get in touch', 'button_url' => '/pages/contact']],
            ],
            'visit' => [
                ['type' => 'hero', 'data' => ['eyebrow' => 'PLAN YOUR VISIT', 'title' => 'You are welcome here', 'body' => 'Find everything you need for a relaxed first visit.', 'button_label' => 'Find a location', 'button_url' => '/locations']],
                ['type' => 'service_times', 'data' => ['title' => 'Join us this week', 'body' => 'Find a time and location that works for you.']],
                ['type' => 'paragraph', 'data' => ['text' => 'Tell guests what to expect when they arrive, including parking, accessibility, and children�s ministry.']],
            ],
            'beliefs' => [
                ['type' => 'hero', 'data' => ['eyebrow' => 'OUR FOUNDATION', 'title' => 'What we believe', 'body' => 'The convictions that shape our worship, life together, and service.', 'button_label' => '', 'button_url' => '']],
                ['type' => 'heading', 'data' => ['text' => 'Rooted in Scripture']],
                ['type' => 'paragraph', 'data' => ['text' => 'Introduce the beliefs and practices you want people to understand.']],
            ],
            'leadership' => [
                ['type' => 'hero', 'data' => ['eyebrow' => 'OUR PEOPLE', 'title' => 'Meet our leaders', 'body' => 'People who serve this ministry with care and conviction.', 'button_label' => '', 'button_url' => '']],
                ['type' => 'paragraph', 'data' => ['text' => 'Add each leader�s name, role, and a short introduction.']],
            ],
            'contact' => [
                ['type' => 'hero', 'data' => ['eyebrow' => 'CONTACT', 'title' => 'Get in touch', 'body' => 'We would love to hear from you and help you take your next step.', 'button_label' => 'Send a message', 'button_url' => '/contact']],
                ['type' => 'paragraph', 'data' => ['text' => 'Share office hours, email address, phone number, and location details.']],
            ],
        ];

        return $starters[$template] ?? $this->editorBlocks(null, $fallback);
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
