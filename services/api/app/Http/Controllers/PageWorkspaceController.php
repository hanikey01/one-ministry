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
    private const MAX_DOCUMENT_BYTES = 750000;

    private const RESERVED = ['admin', 'api', 'departments', 'events', 'give', 'login', 'member', 'pages', 'resources', 'setup', 'staff', 'worship', 'books', 'sermons', 'media', 'dashboard-preview'];

    public function index(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $pages = DB::table('pages')->orderByDesc('updated_at')->get();

        return view('staff.pages.index', compact('settings', 'pages') + ['user' => $request->user()]);
    }

    public function create(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $starter = ['label' => 'New page', 'slug' => ''];

        $features = $this->enabledFeatures();
        $elementRegistry = app(\App\Services\PageElementRegistry::class);
        return view('staff.pages.form', compact('settings', 'starter', 'features') + ['user' => $request->user(), 'page' => null, 'canPublish' => app(PermissionChecker::class)->allows($request->user(), 'content.publish'), 'blocks' => $this->starterDocument('blank'), 'elementCatalog' => $elementRegistry->available($features), 'elementCategories' => $elementRegistry->categories(), 'aiDraftAvailable' => app(ModuleAvailability::class)->visibleToStaff('ai_page_editor', $request->user()), 'aiDraftRoute' => DB::table('ai_task_routes')->where('task_key', 'page_editor')->first()]);
    }

    public function preview(Request $request): View
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'blocks' => ['required', 'string', 'max:'.self::MAX_DOCUMENT_BYTES],
        ]);
        $document = $this->document($input['blocks']);
        $page = (object) [
            'title' => $input['title'],
            'seo_title' => $input['title'],
            'blocks' => json_encode($document, JSON_THROW_ON_ERROR),
            'body' => app(\App\Services\PageDocumentService::class)->plainText($document, $this->enabledFeatures()),
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

        $features = $this->enabledFeatures();
        $elementRegistry = app(\App\Services\PageElementRegistry::class);
        return view('staff.pages.form', compact('settings', 'page', 'revisions', 'canPublish', 'features') + ['user' => $request->user(), 'starter' => null, 'blocks' => $this->editorDocument($page->blocks ?? null), 'isHomepage' => $settings->homepage_page_id === $page->id, 'elementCatalog' => $elementRegistry->available($features), 'elementCategories' => $elementRegistry->categories(), 'aiDraftAvailable' => app(ModuleAvailability::class)->visibleToStaff('ai_page_editor', $request->user()), 'aiDraftRoute' => DB::table('ai_task_routes')->where('task_key', 'page_editor')->first()]);
    }

    /** Replace the active homepage canvas with the current native homepage design. */
    public function applyHomepageDesign(Request $request, string $page): RedirectResponse
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        abort_unless($settings->homepage_page_id === $page, 404);
        $record = DB::table('pages')->where('id', $page)->firstOrFail();
        $document = app(\App\Services\HomepageStarterService::class)->document($this->enabledFeatures());
        $blocks = json_encode($document, JSON_THROW_ON_ERROR);
        $data = ['blocks' => $blocks, 'body' => app(\App\Services\PageDocumentService::class)->plainText($document, $this->enabledFeatures())];
        $revision = (int) DB::table('content_revisions')->where('page_id', $page)->max('revision') + 1;

        DB::transaction(function () use ($request, $page, $record, $data, $revision): void {
            DB::table('pages')->where('id', $page)->update([...$data, 'status' => 'draft', 'published_at' => null, 'published_by' => null, 'review_notes' => null, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            $this->revision($page, $revision, ['title' => $record->title, 'slug' => $record->slug, 'seo_title' => $record->seo_title, 'seo_description' => $record->seo_description, ...$data], $request->user()->id);
            $this->audit($request, 'content.homepage_design_applied', $page, ['revision' => $revision, 'design' => 'three_slide_theme_aware_carousel']);
        });

        return to_route('staff.pages.edit', $page)->with('status', 'The revised homepage design is now on the canvas as a draft. Review it and publish when ready.');
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
        if (! hash_equals((string) $record->updated_at, (string) $request->input('document_version', ''))) {
            throw ValidationException::withMessages(['blocks' => 'This page changed in another session. Reload it before saving so those edits are not overwritten.']);
        }
        $publish = $request->boolean('publish');
        if ($publish) {
            abort_unless(app(PermissionChecker::class)->allows($request->user(), 'content.publish'), 403);
            abort_if($request->user()->email_verified_at === null, 403, 'Verify your email address before publishing.');
        }
        $data = $this->data($request, $page);
        $revision = (int) DB::table('content_revisions')->where('page_id', $page)->max('revision') + 1;
        $savedAt=now();
        DB::transaction(function () use ($request, $page, $data, $revision, $record, $publish, $savedAt): void {
            $updated=DB::table('pages')->where('id', $page)->where('updated_at', $record->updated_at)->update([...$data, 'status' => $publish ? 'published' : 'draft', 'review_notes' => null, 'published_at' => $publish ? $savedAt : null, 'published_by' => $publish ? $request->user()->id : null, 'updated_by' => $request->user()->id, 'updated_at' => $savedAt]);
            if($updated!==1) throw ValidationException::withMessages(['blocks'=>'This page changed while it was being saved. Reload it before trying again.']);
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
                    'document_version' => $savedAt->format('Y-m-d H:i:s'),
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
        $this->editorDocument($record->blocks ?? null);
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
            'blocks' => ['required', 'string', 'max:'.self::MAX_DOCUMENT_BYTES],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ]);
        abort_if(in_array($data['slug'], self::RESERVED, true), 422, 'This page address is reserved.');

        $document = $this->document($data['blocks']);
        $data['blocks'] = json_encode($document, JSON_THROW_ON_ERROR);
        $data['body'] = app(\App\Services\PageDocumentService::class)->plainText($document, $this->enabledFeatures());

        return $data;
    }

    /** Return only a canonical version-2 document to the structured canvas. */
    private function editorDocument(?string $encodedBlocks): array
    {
        if (! is_string($encodedBlocks) || trim($encodedBlocks) === '') {
            throw ValidationException::withMessages(['blocks' => 'This page does not contain a supported version-2 document.']);
        }

        return $this->document($encodedBlocks);
    }

    /** Decode and validate the only supported page-document format. */
    private function document(string $encodedBlocks): array
    {
        try {
            $decoded = json_decode($encodedBlocks, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['blocks' => 'The page layout could not be read. Refresh the editor and try again.']);
        }
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['blocks' => 'The page layout must be a version-2 document.']);
        }

        return app(\App\Services\PageDocumentService::class)->normalise($decoded, $this->enabledFeatures());
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
    public function starterDocument(string $template = 'homepage'): array
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
        $standardSettings = ['content_width' => 'standard', 'padding' => 'none', 'column_gap' => 'regular'];
        $compactSettings = ['content_width' => 'standard', 'padding' => 'none', 'column_gap' => 'regular'];

        if ($template === 'homepage') {
            return app(\App\Services\HomepageStarterService::class)->document($this->enabledFeatures());
        }

        $documents = [
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
            'blank' => [],
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

    private function revision(string $page, int $revision, array $payload, string $user): void
    {
        DB::table('content_revisions')->insert(['id' => (string) Str::uuid(), 'page_id' => $page, 'revision' => $revision, 'payload' => json_encode($payload), 'created_by' => $user, 'created_at' => now()]);
    }

    private function audit(Request $request, string $action, string $page, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'pages', 'subject_id' => $page, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
