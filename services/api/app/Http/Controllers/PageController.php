<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Services\PageDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    private const RESERVED = ['admin', 'api', 'departments', 'events', 'give', 'login', 'member', 'pages', 'resources', 'service-times', 'setup', 'staff', 'worship', 'books', 'sermons', 'media', 'dashboard-preview'];

    public function show(string $slug): JsonResponse
    {
        $page = DB::table('pages')->where('slug', $slug)->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->first();
        abort_unless($page, 404);

        return response()->json(['data' => $this->publicPage($page)]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => DB::table('pages')->latest('updated_at')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->data($request);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $data, $id): void {
            DB::table('pages')->insert([...$data, 'id' => $id, 'status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->revision($id, 1, $data, $request->user()->id);
            $this->audit($request, 'content.page_created', $id, ['slug' => $data['slug']]);
        });

        return response()->json(['data' => DB::table('pages')->where('id', $id)->first()], 201);
    }

    public function update(Request $request, string $page): JsonResponse
    {
        $record = DB::table('pages')->where('id', $page)->first();
        abort_unless($record, 404);
        $data = $this->data($request, $page);
        $revision = (int) DB::table('content_revisions')->where('page_id', $page)->max('revision') + 1;
        DB::transaction(function () use ($request, $page, $data, $revision, $record): void {
            DB::table('pages')->where('id', $page)->update([...$data, 'status' => 'draft', 'review_notes' => null, 'published_at' => null, 'published_by' => null, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            $this->revision($page, $revision, $data, $request->user()->id);
            $this->audit($request, 'content.page_updated', $page, ['revision' => $revision, 'previous_status' => $record->status]);
        });

        return response()->json(['data' => DB::table('pages')->where('id', $page)->first()]);
    }

    public function review(Request $request, string $page): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'rejected'])], 'review_notes' => ['nullable', 'string', 'max:5000']]);
        $query = DB::table('pages')->where('id', $page)->where('status', 'draft');
        abort_unless($query->exists(), 404);
        $changes = ['status' => $data['status'], 'review_notes' => $data['review_notes'] ?? null, 'updated_at' => now()];
        if ($data['status'] === 'published') {
            $changes['published_at'] = now();
            $changes['published_by'] = $request->user()->id;
        }
        DB::transaction(function () use ($request, $page, $query, $changes, $data): void {
            $query->update($changes);
            $this->audit($request, 'content.page_reviewed', $page, ['status' => $data['status']]);
        });

        return response()->json(['data' => DB::table('pages')->where('id', $page)->first()]);
    }

    public function revisions(string $page): JsonResponse
    {
        abort_unless(DB::table('pages')->where('id', $page)->exists(), 404);

        return response()->json(['data' => DB::table('content_revisions')->where('page_id', $page)->orderByDesc('revision')->get(['id', 'revision', 'created_by', 'created_at'])]);
    }

    private function data(Request $request, ?string $page = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'slug' => ['required', 'string', 'max:160', Rule::unique('pages', 'slug')->ignore($page)], 'blocks' => ['required', 'array'], 'blocks.version' => ['required', 'integer', 'in:2'], 'blocks.sections' => ['required', 'array', 'max:60'], 'seo_title' => ['nullable', 'string', 'max:70'], 'seo_description' => ['nullable', 'string', 'max:160']]);
        abort_if(in_array($data['slug'], self::RESERVED, true), 422, 'This page address is reserved.');
        $documents = app(PageDocumentService::class);
        $features = FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();
        $document = $documents->normalise($data['blocks'], $features);
        $data['blocks'] = json_encode($document, JSON_THROW_ON_ERROR);
        $data['body'] = $documents->plainText($document, $features);

        return $data;
    }

    private function revision(string $page, int $revision, array $payload, string $user): void
    {
        DB::table('content_revisions')->insert(['id' => (string) Str::uuid(), 'page_id' => $page, 'revision' => $revision, 'payload' => json_encode($payload), 'created_by' => $user, 'created_at' => now()]);
    }

    private function audit(Request $request, string $action, string $page, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'pages', 'subject_id' => $page, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }

    private function publicPage(object $page): array
    {
        return ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'body' => $page->body, 'blocks' => $page->blocks ? json_decode($page->blocks, true) : [], 'seo_title' => $page->seo_title, 'seo_description' => $page->seo_description, 'published_at' => $page->published_at, 'updated_at' => $page->updated_at];
    }
}
