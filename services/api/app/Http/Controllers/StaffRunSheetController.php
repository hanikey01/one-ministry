<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffRunSheetController extends Controller
{
    public function index(ServiceOccurrence $service): JsonResponse
    {
        return response()->json(['data' => DB::table('service_run_sheet_items')->where('service_occurrence_id', $service->id)->orderBy('position')->get()]);
    }

    public function store(Request $request, ServiceOccurrence $service): JsonResponse
    {
        $d = $request->validate(['kind' => ['sometimes', 'string', 'max:32'], 'title' => ['required', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:10000'], 'position' => ['sometimes', 'integer', 'min:0'], 'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400']]);
        $id = (string) Str::uuid();
        DB::table('service_run_sheet_items')->insert(array_merge($d, ['id' => $id, 'service_occurrence_id' => $service->id, 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['data' => DB::table('service_run_sheet_items')->where('id', $id)->first()], 201);
    }

    public function update(Request $request, ServiceOccurrence $service, string $item): JsonResponse
    {
        $d = $request->validate(['kind' => ['sometimes', 'string', 'max:32'], 'title' => ['sometimes', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:10000'], 'position' => ['sometimes', 'integer', 'min:0'], 'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'], 'status' => ['sometimes', 'in:pending,active,complete,skipped']]);
        $q = DB::table('service_run_sheet_items')->where('id', $item)->where('service_occurrence_id', $service->id);
        abort_unless($q->exists(), 404);
        if (($d['status'] ?? null) === 'active') {
            $d['started_at'] = now();
        } if (($d['status'] ?? null) === 'complete') {
            $d['completed_at'] = now();
        } $d['updated_at'] = now();
        $q->update($d);

        return response()->json(['data' => $q->first()]);
    }

    public function destroy(ServiceOccurrence $service, string $item): JsonResponse
    {
        $deleted = DB::table('service_run_sheet_items')->where('id', $item)->where('service_occurrence_id', $service->id)->delete();
        abort_unless($deleted, 404);

        return response()->json(null, 204);
    }
}
