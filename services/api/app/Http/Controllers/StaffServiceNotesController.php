<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffServiceNotesController extends Controller
{
    public function index(ServiceOccurrence $service): JsonResponse
    {
        return response()->json(['data' => DB::table('service_notes')->where('service_occurrence_id', $service->id)->latest()->limit(200)->get()]);
    }

    public function store(Request $r, ServiceOccurrence $service): JsonResponse
    {
        $d = $r->validate(['content' => ['required', 'string', 'max:10000']]);
        $id = (string) Str::uuid();
        DB::table('service_notes')->insert(['id' => $id, 'service_occurrence_id' => $service->id, 'author_user_id' => $r->user()->id, 'content' => $d['content'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('service_notes')->where('id', $id)->first()], 201);
    }

    public function prompts(ServiceOccurrence $service): JsonResponse
    {
        return response()->json(['data' => DB::table('service_prompts')->where('service_occurrence_id', $service->id)->orderBy('offset_seconds')->get()]);
    }

    public function createPrompt(Request $r, ServiceOccurrence $service): JsonResponse
    {
        $d = $r->validate(['message' => ['required', 'string', 'max:500'], 'run_sheet_item_id' => ['nullable', 'uuid', 'exists:service_run_sheet_items,id'], 'offset_seconds' => ['sometimes', 'integer', 'min:0', 'max:86400']]);
        $id = (string) Str::uuid();
        DB::table('service_prompts')->insert(array_merge($d, ['id' => $id, 'service_occurrence_id' => $service->id, 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['data' => DB::table('service_prompts')->where('id', $id)->first()], 201);
    }

    public function fire(ServiceOccurrence $service, string $prompt): JsonResponse
    {
        $q = DB::table('service_prompts')->where('id', $prompt)->where('service_occurrence_id', $service->id);
        abort_unless($q->exists(), 404);
        $q->update(['fired' => true, 'fired_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => $q->first()]);
    }
}
