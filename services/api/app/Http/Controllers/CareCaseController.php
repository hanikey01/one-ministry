<?php

namespace App\Http\Controllers;

use App\Services\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CareCaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isSuperAdministrator = app(PermissionChecker::class)->isSuperAdministrator($request->user());
        $cases = DB::table('care_cases')
            ->when(! $isSuperAdministrator, fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->where('status', '!=', 'closed')
            ->latest()
            ->get();
        $this->audit($request, 'care.case_list_viewed', 'care_cases', (string) Str::uuid());

        return response()->json(['data' => $cases]);
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prayer_request_id' => ['nullable', 'uuid', 'exists:service_prayer_requests,id', 'unique:care_cases,prayer_request_id'],
            'assigned_to' => ['required', 'uuid', 'exists:users,id'],
        ]);
        $id = (string) Str::uuid();
        DB::table('care_cases')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'care.case_created', 'care_cases', $id);

        return response()->json(['data' => DB::table('care_cases')->where('id', $id)->first()], 201);
    }

    public function show(Request $request, string $case): JsonResponse
    {
        $record = $this->assignedCase($request, $case);
        $notes = DB::table('care_notes')->where('care_case_id', $case)->orderBy('created_at')->get()
            ->map(fn (object $note) => ['id' => $note->id, 'author_user_id' => $note->author_user_id, 'body' => Crypt::decryptString($note->encrypted_body), 'created_at' => $note->created_at]);
        $this->audit($request, 'care.case_viewed', 'care_cases', $case);

        return response()->json(['data' => ['case' => $record, 'notes' => $notes]]);
    }

    public function addNote(Request $request, string $case): JsonResponse
    {
        $this->assignedCase($request, $case);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $id = (string) Str::uuid();
        DB::table('care_notes')->insert(['id' => $id, 'care_case_id' => $case, 'author_user_id' => $request->user()->id, 'encrypted_body' => Crypt::encryptString($data['body']), 'created_at' => now()]);
        $this->audit($request, 'care.note_created', 'care_notes', $id);

        return response()->json(['data' => ['id' => $id, 'body' => $data['body']]], 201);
    }

    public function update(Request $request, string $case): JsonResponse
    {
        $this->assignedCase($request, $case);
        $data = $request->validate(['status' => ['required', 'in:open,in_progress,waiting,closed_no_contact,closed']]);
        DB::table('care_cases')->where('id', $case)->update([...$data, 'closed_at' => str_starts_with($data['status'], 'closed') ? now() : null, 'updated_at' => now()]);
        $this->audit($request, 'care.case_status_updated', 'care_cases', $case);

        return response()->json(['data' => DB::table('care_cases')->where('id', $case)->first()]);
    }

    private function assignedCase(Request $request, string $case): object
    {
        $isSuperAdministrator = app(PermissionChecker::class)->isSuperAdministrator($request->user());
        $record = DB::table('care_cases')
            ->where('id', $case)
            ->when(! $isSuperAdministrator, fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->first();
        abort_unless($record, 404);

        return $record;
    }

    private function audit(Request $request, string $action, string $subjectType, string $subjectId): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'changes_json' => '{}', 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
