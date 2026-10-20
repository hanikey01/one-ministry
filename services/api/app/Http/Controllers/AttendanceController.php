<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function recordAggregate(Request $request, string $service): JsonResponse
    {
        $data = $request->validate(['bucket_started_at' => ['required', 'date'], 'viewer_count' => ['required', 'integer', 'min:0'], 'source' => ['sometimes', 'string', 'max:40']]);
        DB::table('anonymous_viewer_aggregates')->updateOrInsert(
            ['service_occurrence_id' => $service, 'bucket_started_at' => $data['bucket_started_at'], 'source' => $data['source'] ?? 'broadcast_provider'],
            ['id' => (string) Str::uuid(), 'viewer_count' => $data['viewer_count'], 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['data' => ['status' => 'recorded']], 201);
    }

    public function presence(Request $request, string $service): JsonResponse
    {
        $data = $request->validate(['person_id' => ['required', 'uuid', 'exists:people,id'], 'session_id' => ['nullable', 'uuid'], 'left' => ['sometimes', 'boolean']]);
        $id = $data['session_id'] ?? (string) Str::uuid();
        $now = now();
        DB::table('presence_sessions')->updateOrInsert(
            ['id' => $id],
            ['service_occurrence_id' => $service, 'person_id' => $data['person_id'], 'joined_at' => $now, 'last_seen_at' => $now, 'left_at' => ($data['left'] ?? false) ? $now : null, 'created_at' => $now, 'updated_at' => $now]
        );

        return response()->json(['data' => ['session_id' => $id, 'status' => 'recorded']]);
    }

    public function correct(Request $request, string $attendance): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:present,absent'], 'reason' => ['required', 'string', 'max:2000']]);
        $record = DB::table('attendance_records')->where('id', $attendance)->first();
        abort_unless($record, 404);
        DB::transaction(function () use ($request, $data, $record, $attendance): void {
            DB::table('attendance_corrections')->insert([
                'id' => (string) Str::uuid(), 'attendance_record_id' => $attendance, 'actor_user_id' => $request->user()->id,
                'previous_status' => $record->status, 'new_status' => $data['status'], 'reason' => $data['reason'], 'created_at' => now(),
            ]);
            DB::table('attendance_records')->where('id', $attendance)->update([
                'status' => $data['status'], 'corrected_by' => $request->user()->id, 'correction_reason' => $data['reason'], 'updated_at' => now(),
            ]);
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => 'attendance.corrected',
                'subject_type' => 'attendance_records', 'subject_id' => $attendance, 'changes_json' => json_encode(['from' => $record->status, 'to' => $data['status']]),
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });

        return response()->json(['data' => DB::table('attendance_records')->where('id', $attendance)->first()]);
    }
}
