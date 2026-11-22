<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StaffPrayerController extends Controller
{
    public function index(Request $request): View
    {
        $prayerRequests = DB::table('prayer_requests')
            ->join('people', 'people.id', '=', 'prayer_requests.person_id')
            ->select('prayer_requests.*', 'people.first_name', 'people.last_name', 'people.email', 'people.phone')
            ->whereIn('prayer_requests.status', ['received', 'in_progress', 'removal_requested'])
            ->orderBy('prayer_requests.created_at')
            ->paginate(20);

        $prayerRequests->getCollection()->transform(function (object $prayer): object {
            try {
                $prayer->text = Crypt::decryptString((string) $prayer->prayer_text_encrypted);
                $prayer->unreadable = false;
            } catch (\Throwable) {
                $prayer->text = null;
                $prayer->unreadable = true;
            }

            return $prayer;
        });

        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(),
            'actor_user_id' => $request->user()->id,
            'action' => 'prayer.requests_read',
            'subject_type' => 'prayer_requests',
            'subject_id' => (string) Str::uuid(),
            'changes_json' => json_encode(['count' => $prayerRequests->count()], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return view('staff.prayer-requests', [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'prayerRequests' => $prayerRequests,
        ]);
    }
}
