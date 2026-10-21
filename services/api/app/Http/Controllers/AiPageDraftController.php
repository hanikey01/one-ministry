<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiPageDraftService;
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
        ]);

        try {
            $draft = $drafts->generate([
                'brief' => trim($input['brief']),
                'title' => isset($input['title']) ? trim($input['title']) : null,
                'public_details' => isset($input['public_details']) ? trim($input['public_details']) : null,
            ], $request->user(), $request->ip());
            $draft['slug'] = Str::slug($draft['title']) ?: 'page-'.Str::lower(Str::random(6));

            return response()->json(['data' => $draft]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['error' => ['code' => 'ai_page_draft_unavailable', 'message' => $exception->getMessage()]], 503);
        }
    }

    public function reviseSection(Request $request, AiPageDraftService $drafts): JsonResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instruction' => ['required', 'string', 'min:10', 'max:1500'],
            'page_purpose' => ['nullable', 'string', 'max:3000'],
            'selected_index' => ['required', 'integer', 'min:0', 'max:59'],
            'blocks' => ['required', 'string', 'max:100000'],
        ]);
        $blocks = json_decode($input['blocks'], true);
        if (! is_array($blocks) || count($blocks) > 60 || ! array_is_list($blocks) || ! array_key_exists((int) $input['selected_index'], $blocks)) {
            throw ValidationException::withMessages(['blocks' => 'The page sections could not be read. Refresh the editor and try again.']);
        }

        try {
            return response()->json(['data' => $drafts->reviseSection([
                'title' => trim($input['title']), 'instruction' => trim($input['instruction']),
                'page_purpose' => trim($input['page_purpose'] ?? ''),
                'blocks' => $blocks, 'selected_index' => (int) $input['selected_index'],
            ], $request->user(), $request->ip())]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['error' => ['code' => 'ai_page_section_revision_unavailable', 'message' => $exception->getMessage()]], 503);
        }
    }
}
