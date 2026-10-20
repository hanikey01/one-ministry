<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffVolunteerController extends Controller
{
    public function teams(): JsonResponse
    {
        return response()->json(['data' => DB::table('volunteer_teams')->orderBy('name')->get()]);
    }

    public function createTeam(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000']]);
        $id = (string) Str::uuid();
        DB::table('volunteer_teams')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('volunteer_teams')->where('id', $id)->first()], 201);
    }

    public function assignments(): JsonResponse
    {
        return response()->json(['data' => DB::table('volunteer_assignments')->join('volunteer_teams', 'volunteer_teams.id', '=', 'volunteer_assignments.team_id')->join('people', 'people.id', '=', 'volunteer_assignments.person_id')->orderBy('starts_at')->select('volunteer_assignments.*', 'volunteer_teams.name as team_name', 'people.first_name', 'people.last_name')->paginate(50)]);
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'uuid', 'exists:volunteer_teams,id'],
            'person_id' => ['required', 'uuid', 'exists:people,id'],
            'service_occurrence_id' => ['nullable', 'uuid', 'exists:service_occurrences,id'],
            'role' => ['required', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'reminder_at' => ['nullable', 'date', 'before:starts_at'],
        ]);
        $conflict = DB::table('volunteer_availability')->where('person_id', $data['person_id'])->where('state', 'unavailable')->where('starts_at', '<', $data['ends_at'] ?? $data['starts_at'])->where('ends_at', '>', $data['starts_at'])->exists();
        abort_if($conflict, 422, 'This volunteer is unavailable for the selected time.');
        $id = (string) Str::uuid();
        DB::table('volunteer_assignments')->insert([...$data, 'id' => $id, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('volunteer_assignments')->where('id', $id)->first()], 201);
    }

    public function updateAssignment(Request $request, string $assignment): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'in:scheduled,accepted,declined,cancelled,completed'], 'substituted_by' => ['nullable', 'uuid', 'exists:people,id']]);
        $query = DB::table('volunteer_assignments')->where('id', $assignment);
        abort_unless($query->exists(), 404);
        $query->update([...$data, 'updated_at' => now()]);

        return response()->json(['data' => $query->first()]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate(['person_id' => ['required', 'uuid', 'exists:people,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'state' => ['required', 'in:available,unavailable'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $id = (string) Str::uuid();
        DB::table('volunteer_availability')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('volunteer_availability')->where('id', $id)->first()], 201);
    }

    public function requestSubstitute(Request $request, string $assignment): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $record = DB::table('volunteer_assignments')->where('id', $assignment)->first();
        abort_unless($record, 404);
        $id = (string) Str::uuid();
        DB::table('volunteer_substitution_requests')->insert(['id' => $id, 'assignment_id' => $assignment, 'requested_by' => $record->person_id, 'reason' => $data['reason'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('volunteer_assignments')->where('id', $assignment)->update(['status' => 'substitution_requested', 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'open']], 201);
    }

    public function resolveSubstitute(Request $request, string $substitution): JsonResponse
    {
        $data = $request->validate(['replacement_person_id' => ['required', 'uuid', 'exists:people,id']]);
        $query = DB::table('volunteer_substitution_requests')->where('id', $substitution)->where('status', 'open');
        $record = $query->first();
        abort_unless($record, 404);
        DB::transaction(function () use ($query, $record, $data): void {
            $query->update(['replacement_person_id' => $data['replacement_person_id'], 'status' => 'approved', 'updated_at' => now()]);
            DB::table('volunteer_assignments')->where('id', $record->assignment_id)->update(['substituted_by' => $data['replacement_person_id'], 'status' => 'substituted', 'updated_at' => now()]);
        });

        return response()->json(['data' => $query->first()]);
    }
}
