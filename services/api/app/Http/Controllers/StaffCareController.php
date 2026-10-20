<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffCareController extends Controller
{
    public function queue(): JsonResponse
    {
        return response()->json(['data' => DB::table('follow_up_tasks')->where(function ($q): void {
            $q->whereIn('kind', ['visitor_follow_up', 'prayer'])->orWhere('kind', 'like', 'journey_%');
        })->where('status', '!=', 'completed')->orderByRaw('due_at is null')->orderBy('due_at')->paginate(50)]);
    }

    public function update(Request $r, string $task): JsonResponse
    {
        $d = $r->validate(['assigned_to' => ['nullable', 'uuid', 'exists:users,id'], 'status' => ['sometimes', 'in:open,in_progress,completed'], 'due_at' => ['nullable', 'date'], 'summary' => ['sometimes', 'string', 'max:2000']]);
        if (($d['status'] ?? null) === 'completed') {
            $d['completed_at'] = now();
        }$d['updated_at'] = now();
        $q = DB::table('follow_up_tasks')->where('id', $task);
        abort_unless($q->exists(), 404);
        $q->update($d);

        return response()->json(['data' => $q->first()]);
    }

    public function addMember(Request $r, string $group): JsonResponse
    {
        $d = $r->validate(['person_id' => ['required', 'uuid', 'exists:people,id'], 'role' => ['sometimes', 'in:member,leader']]);
        $id = (string) Str::uuid();
        DB::table('group_memberships')->updateOrInsert(['group_id' => $group, 'person_id' => $d['person_id']], ['id' => $id, 'role' => $d['role'] ?? 'member', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['group_id' => $group, 'person_id' => $d['person_id'], 'role' => $d['role'] ?? 'member']], 201);
    }

    public function members(string $group): JsonResponse
    {
        return response()->json(['data' => DB::table('group_memberships')->join('people', 'people.id', '=', 'group_memberships.person_id')->where('group_id', $group)->get(['group_memberships.id', 'person_id', 'role', 'first_name', 'last_name', 'email'])]);
    }

    public function removeMember(string $group, string $membership): JsonResponse
    {
        $n = DB::table('group_memberships')->where('group_id', $group)->where('id', $membership)->delete();
        abort_unless($n, 404);

        return response()->json(null, 204);
    }
}
