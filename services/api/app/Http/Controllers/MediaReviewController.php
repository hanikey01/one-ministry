<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaReviewController extends Controller
{
    public function recordings(): JsonResponse
    {
        return response()->json(['data' => DB::table('meeting_recordings')->join('meetings', 'meetings.id', '=', 'meeting_recordings.meeting_id')->where('meeting_recordings.status', 'pending_review')->latest('meeting_recordings.created_at')->get(['meeting_recordings.id', 'meeting_recordings.meeting_id', 'meeting_recordings.provider_key', 'meeting_recordings.status', 'meetings.title'])]);
    }

    public function reviewRecording(Request $request, string $recording): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'review_notes' => ['nullable', 'string', 'max:5000']]);
        $query = DB::table('meeting_recordings')->where('id', $recording)->where('status', 'pending_review');
        abort_unless($query->exists(), 404);
        $query->update([...$data, 'reviewed_by' => $request->user()->id, 'updated_at' => now()]);
        $this->audit($request, 'media.recording_reviewed', 'meeting_recordings', $recording, $data);

        return response()->json(['data' => DB::table('meeting_recordings')->where('id', $recording)->first()]);
    }

    public function drafts(): JsonResponse
    {
        return response()->json(['data' => DB::table('media_assets')->whereIn('status', ['draft', 'rejected'])->latest()->get()]);
    }

    public function publish(Request $request, string $asset): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:published,rejected'], 'description' => ['nullable', 'string']]);
        $query = DB::table('media_assets')->where('id', $asset);
        abort_unless($query->exists(), 404);
        $query->update([...$data, 'published_at' => $data['status'] === 'published' ? now() : null, 'updated_at' => now()]);
        $this->audit($request, 'media.asset_reviewed', 'media_assets', $asset, ['status' => $data['status']]);

        return response()->json(['data' => $query->first()]);
    }

    private function audit(Request $request, string $action, string $type, string $id, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
