<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => DB::table('service_templates')->where('active', true)->orderBy('title')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'timezone'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'default_duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'run_sheet_defaults' => ['nullable', 'array'],
        ]);
        $id = (string) Str::uuid();
        DB::table('service_templates')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('service_templates')->where('id', $id)->first()], 201);
    }

    public function createOccurrence(Request $request, string $template): JsonResponse
    {
        $record = DB::table('service_templates')->where('id', $template)->where('active', true)->first();
        abort_unless($record, 404);
        $data = $request->validate(['starts_at' => ['required', 'date'], 'rehearsal_mode' => ['sometimes', 'boolean']]);
        $startsAt = now()->parse($data['starts_at']);
        $service = ServiceOccurrence::create([
            'service_template_id' => $record->id, 'location_id' => $record->location_id, 'title' => $record->title,
            'timezone' => $record->timezone, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addMinutes($record->default_duration_minutes),
            'rehearsal_mode' => (bool) ($data['rehearsal_mode'] ?? false),
        ]);

        foreach (json_decode($record->run_sheet_defaults ?? '[]', true) ?: [] as $position => $item) {
            DB::table('service_run_sheet_items')->insert([
                'id' => (string) Str::uuid(), 'service_occurrence_id' => $service->id, 'kind' => $item['kind'] ?? 'segment',
                'title' => $item['title'] ?? 'Service segment', 'notes' => $item['notes'] ?? null, 'position' => $position,
                'duration_seconds' => $item['duration_seconds'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json(['data' => $service], 201);
    }
}
