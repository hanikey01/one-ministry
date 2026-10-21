<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->person($request)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'consent_to_contact' => ['sometimes', 'boolean'],
            'birthday_collection_consent' => ['nullable', 'boolean'],
            'birthday_month' => ['nullable', 'integer', 'between:1,12', 'required_if:birthday_collection_consent,1'],
            'birthday_day' => ['nullable', 'integer', 'between:1,31', 'required_if:birthday_collection_consent,1'],
        ]);
        $person = $this->person($request);
        if (array_key_exists('birthday_collection_consent', $data)) {
            if ($data['birthday_collection_consent']) {
                if (! checkdate((int) $data['birthday_month'], (int) $data['birthday_day'], 2000)) {
                    throw ValidationException::withMessages(['birthday_day' => 'Choose a valid day for the selected month.']);
                }
                $data['birthday'] = CarbonImmutable::create(2000, (int) $data['birthday_month'], (int) $data['birthday_day'], 0, 0, 0, 'UTC')->toDateString();
            } else {
                $data['birthday'] = null;
            }
        }
        unset($data['birthday_collection_consent'], $data['birthday_month'], $data['birthday_day']);
        DB::table('people')->where('id', $person->id)->update([...$data, 'updated_at' => now()]);

        return response()->json(['data' => $this->person($request)]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $person = $this->person($request);

        return response()->json(['data' => DB::table('notification_preferences')->where('person_id', $person->id)->first()]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $data = $request->validate([
            'email_marketing' => ['sometimes', 'boolean'],
            'sms_marketing' => ['sometimes', 'boolean'],
            'birthday_staff_reminder' => ['sometimes', 'boolean'],
            'birthday_public_recognition' => ['sometimes', 'boolean'],
            'quiet_hours_timezone' => ['nullable', 'timezone'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);
        if (! $person->birthday) {
            $data['birthday_staff_reminder'] = false;
            $data['birthday_public_recognition'] = false;
        }
        DB::table('notification_preferences')->updateOrInsert(
            ['person_id' => $person->id],
            [...$data, 'id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]
        );

        return $this->preferences($request);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $modules = app(\App\Services\ModuleAvailability::class);

        return response()->json(['data' => [
            'groups' => $modules->enabled('groups') ? DB::table('group_memberships')->join('groups', 'groups.id', '=', 'group_memberships.group_id')->where('person_id', $person->id)->get(['groups.id', 'groups.name', 'group_memberships.role']) : collect(),
            'events' => DB::table('event_registrations')->join('events', 'events.id', '=', 'event_registrations.event_id')->where('person_id', $person->id)->where('event_registrations.status', 'registered')->orderBy('events.starts_at')->get(['events.id', 'events.title', 'events.starts_at', 'events.location']),
            'volunteer_assignments' => DB::table('volunteer_assignments')->join('volunteer_teams', 'volunteer_teams.id', '=', 'volunteer_assignments.team_id')->where('person_id', $person->id)->where('starts_at', '>=', now())->orderBy('starts_at')->get(['volunteer_assignments.id', 'volunteer_assignments.role', 'volunteer_assignments.starts_at', 'volunteer_assignments.status', 'volunteer_teams.name as team_name']),
            'attendance' => DB::table('attendance_records')->join('service_occurrences', 'service_occurrences.id', '=', 'attendance_records.service_occurrence_id')->where('person_id', $person->id)->latest('attendance_records.observed_at')->limit(10)->get(['service_occurrences.title', 'attendance_records.status', 'attendance_records.observed_at']),
            'tasks' => DB::table('follow_up_tasks')->where('person_id', $person->id)->where('status', '!=', 'completed')->get(['id', 'summary', 'status', 'due_at']),
        ]]);
    }

    public function donations(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $items = DB::table('donations')
            ->join('giving_funds', 'giving_funds.id', '=', 'donations.fund_id')
            ->leftJoin('donation_receipts', 'donation_receipts.donation_id', '=', 'donations.id')
            ->where('donations.person_id', $person->id)
            ->whereIn('donations.status', ['confirmed', 'refunded', 'reversed'])
            ->latest('donations.confirmed_at')
            ->get([
                'donations.id', 'donations.amount_minor', 'donations.currency', 'donations.status',
                'donations.confirmed_at', 'giving_funds.name as fund_name',
                'donation_receipts.receipt_number', 'donation_receipts.sent_at as receipt_sent_at',
            ]);

        return response()->json(['data' => $items]);
    }

    private function person(Request $request): object
    {
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        if ($person) {
            return $person;
        }
        $id = (string) Str::uuid();
        $names = preg_split('/\s+/', trim($request->user()->name), 2);
        DB::table('people')->insert([
            'id' => $id, 'user_id' => $request->user()->id, 'first_name' => $names[0] ?: 'Member',
            'last_name' => $names[1] ?? null, 'email' => $request->user()->email, 'status' => 'member',
            'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('people')->where('id', $id)->first();
    }
}
