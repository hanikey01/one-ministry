<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupAttendanceController extends Controller
{
    public function index(string $group): JsonResponse
    {
        abort_unless(DB::table('groups')->where('id', $group)->exists(), 404);
        $gatherings = DB::table('group_gatherings')->where('group_id', $group)->latest('gathered_at')->limit(30)->get();
        $attendance = DB::table('group_attendance_records')->join('people', 'people.id', '=', 'group_attendance_records.person_id')->whereIn('group_gathering_id', $gatherings->pluck('id'))->get(['group_attendance_records.group_gathering_id', 'group_attendance_records.person_id', 'group_attendance_records.status', 'people.first_name', 'people.last_name']);

        return response()->json(['data' => $gatherings->map(fn ($gathering) => [...(array) $gathering, 'attendance' => $attendance->where('group_gathering_id', $gathering->id)->values()])]);
    }

    public function store(Request $request, string $group): JsonResponse
    {
        $data = $request->validate(['gathered_at' => ['required', 'date'], 'title' => ['nullable', 'string', 'max:160'], 'present_person_ids' => ['required', 'array'], 'present_person_ids.*' => ['uuid', 'exists:people,id']]);
        $memberIds = DB::table('group_memberships')->where('group_id', $group)->whereIn('person_id', $data['present_person_ids'])->pluck('person_id')->all();
        abort_unless(count($memberIds) === count(array_unique($data['present_person_ids'])), 422, 'Attendance can only be recorded for current group members.');
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $data, $group, $memberIds, $id): void {
            DB::table('group_gatherings')->insert(['id' => $id, 'group_id' => $group, 'gathered_at' => $data['gathered_at'], 'title' => $data['title'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($memberIds as $personId) {
                DB::table('group_attendance_records')->insert(['id' => (string) Str::uuid(), 'group_gathering_id' => $id, 'person_id' => $personId, 'status' => 'present', 'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['data' => ['gathering_id' => $id, 'present_count' => count($memberIds)]], 201);
    }
}
