<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffCareController extends Controller
{
    public function queue(): JsonResponse
    {
        return response()->json(['data' => DB::table('follow_up_tasks')->where(function ($q): void {
            $q->whereIn('kind', ['contact_follow_up', 'prayer', 'next_step']);
        })->whereIn('status', ['open', 'in_progress'])->orderByRaw('due_at is null')->orderBy('due_at')->paginate(50)]);
    }

    public function update(Request $r, string $task): JsonResponse
    {
        $d = $r->validate(['assigned_to' => ['nullable', 'uuid', 'exists:users,id'], 'status' => ['sometimes', 'in:open,in_progress,completed,closed_no_contact'], 'due_at' => ['nullable', 'date'], 'summary' => ['sometimes', 'string', 'max:2000']]);
        if (in_array($d['status'] ?? null, ['completed', 'closed_no_contact'], true)) {
            $d['completed_at'] = now();
        }$d['updated_at'] = now();
        $q = DB::table('follow_up_tasks')->where('id', $task);
        abort_unless($q->exists(), 404);
        $q->update($d);

        return response()->json(['data' => $q->first()]);
    }

}
