<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberJourneyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $personId = DB::table('people')->where('user_id', $request->user()->id)->value('id');

        return response()->json(['data' => $personId ? DB::table('member_journeys')->where('person_id', $personId)->latest()->get(['id', 'type', 'status', 'consent_to_contact', 'created_at', 'completed_at']) : []]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::exists('member_journey_options', 'key')->where(fn ($query) => $query->where('active', true))], 'consent_to_contact' => ['accepted']]);
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        abort_unless($person, 422, 'Complete your member profile before starting a journey.');
        $duplicate = DB::table('member_journeys')->where('person_id', $person->id)->where('type', $data['type'])->whereIn('status', ['requested', 'contacted', 'enrolled'])->exists();
        abort_if($duplicate, 409, 'You already have an active request for this journey.');

        $journeyId = (string) Str::uuid();
        $taskId = (string) Str::uuid();
        $label = DB::table('member_journey_options')->where('key', $data['type'])->value('label');
        DB::transaction(function () use ($person, $request, $journeyId, $taskId, $data, $label): void {
            DB::table('follow_up_tasks')->insert(['id' => $taskId, 'person_id' => $person->id, 'kind' => 'journey_'.$data['type'], 'status' => 'open', 'summary' => 'Member requested '.$label.' follow-up.', 'due_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('member_journeys')->insert(['id' => $journeyId, 'person_id' => $person->id, 'user_id' => $request->user()->id, 'follow_up_task_id' => $taskId, 'type' => $data['type'], 'status' => 'requested', 'consent_to_contact' => true, 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => ['id' => $journeyId, 'type' => $data['type'], 'status' => 'requested']], 201);
    }
}
