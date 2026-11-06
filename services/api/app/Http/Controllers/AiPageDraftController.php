<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Services\Ai\AiPageDraftService;
use App\Services\PageDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiPageDraftController extends Controller
{
    public function __invoke(Request $request, AiPageDraftService $drafts): JsonResponse
    {
        $input = $request->validate([
            'brief' => ['required', 'string', 'min:20', 'max:3000'],
            'title' => ['nullable', 'string', 'max:200'],
            'public_details' => ['nullable', 'string', 'max:5000'],
            'page_goal' => ['nullable', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'max:300'],
            'visual_direction' => ['nullable', 'string', 'max:1000'],
            'content_density' => ['nullable', 'in:concise,balanced,detailed'],
            'primary_action' => ['nullable', 'string', 'max:500'],
            'required_sections' => ['nullable', 'string', 'max:1500'],
            'forbidden_patterns' => ['nullable', 'string', 'max:1500'],
            'brand_constraints' => ['nullable', 'string', 'max:1500'],
        ]);

        try {
            $draft = $drafts->generate([
                'brief' => trim($input['brief']),
                'title' => isset($input['title']) ? trim($input['title']) : null,
                'public_details' => isset($input['public_details']) ? trim($input['public_details']) : null,
                'page_goal' => trim($input['page_goal'] ?? ''),
                'audience' => trim($input['audience'] ?? ''),
                'tone' => trim($input['tone'] ?? ''),
                'visual_direction' => trim($input['visual_direction'] ?? ''),
                'content_density' => $input['content_density'] ?? 'balanced',
                'primary_action' => trim($input['primary_action'] ?? ''),
                'required_sections' => trim($input['required_sections'] ?? ''),
                'forbidden_patterns' => trim($input['forbidden_patterns'] ?? ''),
                'brand_constraints' => trim($input['brand_constraints'] ?? ''),
            ], $request->user(), $request->ip());
            $draft['slug'] = Str::slug($draft['title']) ?: 'page-'.Str::lower(Str::random(6));

            return response()->json(['data' => $draft]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['error' => ['code' => 'ai_page_draft_unavailable', 'message' => $exception->getMessage()]], 503);
        }
    }

    public function reviseObject(Request $request, AiPageDraftService $drafts, PageDocumentService $documents): JsonResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instruction' => ['required', 'string', 'min:10', 'max:1500'],
            'page_purpose' => ['nullable', 'string', 'max:3000'],
            'kind' => ['required', 'in:section,column,element'],
            'section' => ['required', 'integer', 'min:0', 'max:59'],
            'column' => ['nullable', 'required_unless:kind,section', 'integer', 'min:0', 'max:11'],
            'element' => ['nullable', 'required_if:kind,element', 'integer', 'min:0', 'max:59'],
            'document' => ['required', 'string', 'max:100000'],
        ]);

        try {
            $document = json_decode($input['document'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['document' => 'The page document could not be read. Refresh the editor and try again.']);
        }

        $features = FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();
        $document = $documents->normalise($document, $features);
        $section = $document['sections'][(int) $input['section']] ?? null;
        if (! $section) throw ValidationException::withMessages(['section' => 'Choose a section that still exists.']);
        if ($input['kind'] !== 'section') {
            $column = $section['columns'][(int) $input['column']] ?? null;
            if (! $column) throw ValidationException::withMessages(['column' => 'Choose a column that still exists.']);
            if ($input['kind'] === 'element' && ! isset($column['elements'][(int) $input['element']])) {
                throw ValidationException::withMessages(['element' => 'Choose an element that still exists.']);
            }
        }

        try {
            return response()->json(['data' => $drafts->reviseObject([
                'title' => trim($input['title']),
                'instruction' => trim($input['instruction']),
                'page_purpose' => trim($input['page_purpose'] ?? ''),
                'kind' => $input['kind'],
                'section' => (int) $input['section'],
                'column' => isset($input['column']) ? (int) $input['column'] : null,
                'element' => isset($input['element']) ? (int) $input['element'] : null,
                'document' => $document,
            ], $request->user(), $request->ip())]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['error' => ['code' => 'ai_page_object_revision_unavailable', 'message' => $exception->getMessage()]], 503);
        }
    }
}
