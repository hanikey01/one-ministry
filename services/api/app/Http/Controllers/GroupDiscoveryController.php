<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupDiscoveryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => DB::table('groups')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'description'])]);
    }

    public function request(Request $request, string $group): JsonResponse
    {
        abort_unless(DB::table('groups')->where('id', $group)->where('status', 'active')->exists(), 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:254'], 'message' => ['nullable', 'string', 'max:2000']]);
        $personId = $request->user()?->id ? DB::table('people')->where('user_id', $request->user()->id)->value('id') : null;
        $id = (string) Str::uuid();
        DB::table('group_join_requests')->insert([...$data, 'id' => $id, 'group_id' => $group, 'person_id' => $personId, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'pending']], 201);
    }

    public function staffIndex(string $group): JsonResponse
    {
        return response()->json(['data' => DB::table('group_join_requests')->where('group_id', $group)->latest()->get()]);
    }

    public function review(Request $request, string $group, string $joinRequest): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,declined']]);
        $record = DB::table('group_join_requests')->where('id', $joinRequest)->where('group_id', $group)->first();
        abort_unless($record, 404);
        DB::transaction(function () use ($request, $data, $record, $group, $joinRequest): void {
            DB::table('group_join_requests')->where('id', $joinRequest)->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            if ($data['status'] === 'approved' && $record->person_id) {
                DB::table('group_memberships')->insertOrIgnore(['id' => (string) Str::uuid(), 'group_id' => $group, 'person_id' => $record->person_id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['data' => DB::table('group_join_requests')->where('id', $joinRequest)->first()]);
    }
}
