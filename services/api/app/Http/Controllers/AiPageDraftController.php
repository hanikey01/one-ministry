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
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'brief' => ['required', 'string', 'min:20', 'max:12000'],
            'title' => ['nullable', 'string', 'max:200'],
            'public_details' => ['nullable', 'string', 'max:15000'],
            'page_goal' => ['nullable', 'string', 'max:3000'],
            'audience' => ['nullable', 'string', 'max:1500'],
            'tone' => ['nullable', 'string', 'max:1000'],
            'visual_direction' => ['nullable', 'string', 'max:4000'],
            'content_density' => ['nullable', 'in:concise,balanced,detailed'],
            'primary_action' => ['nullable', 'string', 'max:1500'],
            'required_sections' => ['nullable', 'string', 'max:6000'],
            'forbidden_patterns' => ['nullable', 'string', 'max:4000'],
            'brand_constraints' => ['nullable', 'string', 'max:4000'],
        ]);

        $key = $request->header('Idempotency-Key', (string) Str::uuid());
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_-]{1,80}$/', $key)) throw ValidationException::withMessages(['idempotency_key' => 'Use a valid request key.']);
        $generations = app(\App\Services\Ai\AiPageGenerationService::class);
        $row = $generations->enqueue($input, $request->user(), $key);

        return response()->json(['data' => $generations->view($row)], 202);
    }

    public function generationStatus(Request $request, string $generation, \App\Services\Ai\AiPageGenerationService $generations): JsonResponse
    {
        return response()->json(['data' => $generations->view($generations->owned($generation, $request->user()))])->header('Cache-Control', 'no-store');
    }

    public function processGeneration(Request $request, string $generation, \App\Services\Ai\AiPageGenerationService $generations): JsonResponse
    {
        $row = $generations->processOwned($generation, $request->user());

        return response()->json(['data' => $generations->view($row)])->header('Cache-Control', 'no-store');
    }

    public function cancelGeneration(Request $request, string $generation, \App\Services\Ai\AiPageGenerationService $generations): JsonResponse
    {
        $generations->cancel($generation, $request->user());

        return response()->json(['data' => ['id' => $generation, 'state' => 'cancelled']]);
    }

    public function reviseObject(Request $request, AiPageDraftService $drafts, PageDocumentService $documents): JsonResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instruction' => ['required', 'string', 'min:10', 'max:5000'],
            'page_purpose' => ['nullable', 'string', 'max:6000'],
            'kind' => ['required', 'in:section,column,element'],
            'section' => ['required', 'integer', 'min:0', 'max:59'],
            'column' => ['nullable', 'required_unless:kind,section', 'integer', 'min:0', 'max:11'],
            'element' => ['nullable', 'required_if:kind,element', 'integer', 'min:0', 'max:59'],
            'document' => ['required', 'string', 'max:750000'],
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
