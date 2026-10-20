<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InteractiveMeetingController extends Controller
{
    public function show(Request $request, string $meeting): View
    {
        $room = DB::table('meetings')
            ->where('id', $meeting)
            ->where('provider', 'zoom_video_sdk')
            ->first(['id', 'title', 'status', 'starts_at', 'recording_notice', 'entry_policy', 'participant_media_policy', 'screen_share_policy', 'chat_policy']);
        abort_unless($room, 404);

        return view('meetings.show', [
            'meeting' => $room,
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
        ]);
    }
}
