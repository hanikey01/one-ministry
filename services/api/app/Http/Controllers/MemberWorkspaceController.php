<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberWorkspaceController extends Controller
{
    public function home(Request $request): View
    {
        return $this->page($request, 'member.home', 'My ministry');
    }

    public function events(Request $request): View
    {
        return $this->page($request, 'member.events', 'My events');
    }

    public function groups(Request $request): View
    {
        return $this->page($request, 'member.groups', 'My groups', 'groups');
    }

    public function serving(Request $request): View
    {
        return $this->page($request, 'member.serving', 'Serving');
    }

    public function activity(Request $request): View
    {
        return $this->page($request, 'member.activity', 'My participation');
    }

    public function nextSteps(Request $request): View
    {
        return $this->page($request, 'member.next-steps', 'My next steps');
    }

    public function prayerPage(Request $request): View
    {
        return $this->page($request, 'member.prayer', 'Prayer');
    }

    public function giving(Request $request): View
    {
        return $this->page($request, 'member.giving', 'Giving', 'giving');
    }

    public function profilePage(Request $request): View
    {
        return $this->page($request, 'member.profile', 'Profile & preferences');
    }

    private function page(Request $request, string $view, string $title, ?string $requiredFeature = null): View
    {
        $person = $this->person($request);
        $features = DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all();
        abort_if($requiredFeature && ! in_array($requiredFeature, $features, true), 404);
        $groups = in_array('groups', $features, true) ? DB::table('group_memberships')->join('groups', 'groups.id', '=', 'group_memberships.group_id')->where('person_id', $person->id)->get(['groups.name', 'group_memberships.role']) : collect();
        $events = DB::table('event_registrations')->join('events', 'events.id', '=', 'event_registrations.event_id')->where('person_id', $person->id)->where('event_registrations.status', 'registered')->where('events.starts_at', '>=', now())->orderBy('events.starts_at')->get(['events.title', 'events.starts_at', 'events.location']);
        $serving = DB::table('volunteer_assignments')->join('volunteer_teams', 'volunteer_teams.id', '=', 'volunteer_assignments.team_id')->where('person_id', $person->id)->where('starts_at', '>=', now())->orderBy('starts_at')->get(['volunteer_assignments.role', 'volunteer_assignments.starts_at', 'volunteer_assignments.status', 'volunteer_teams.name as team_name']);
        $donations = in_array('giving', $features, true) ? DB::table('donations')->join('giving_funds', 'giving_funds.id', '=', 'donations.fund_id')->where('donations.person_id', $person->id)->whereIn('donations.status', ['confirmed', 'refunded', 'reversed'])->latest('confirmed_at')->limit(10)->get(['amount_minor', 'currency', 'status', 'confirmed_at', 'giving_funds.name as fund_name']) : collect();
        $attendance = DB::table('attendance_records')
            ->where('person_id', $person->id)
            ->whereIn('status', ['present', 'confirmed', 'self_reported', 'corrected'])
            ->latest('observed_at')
            ->limit(6)
            ->get(['source', 'status', 'observed_at']);
        $journeys = DB::table('member_journeys')->where('person_id', $person->id)->latest('created_at')->get(['type', 'status', 'created_at', 'completed_at']);
        $prayers = DB::table('prayer_requests')->where('person_id', $person->id)->latest('created_at')->get(['id', 'visibility', 'status', 'created_at']);

        return view($view, [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'person' => $person,
            'preferences' => DB::table('notification_preferences')->where('person_id', $person->id)->first(),
            'groups' => $groups, 'events' => $events, 'serving' => $serving, 'donations' => $donations,
            'attendance' => $attendance, 'journeys' => $journeys, 'prayers' => $prayers,
            'nextEvent' => $events->first(),
            'features' => $features,
            'metrics' => ['groups' => $groups->count(), 'events' => $events->count(), 'serving' => $serving->count(), 'journeys' => $journeys->whereIn('status', ['requested', 'contacted', 'enrolled'])->count()],
            'title' => $title,
        ]);
    }

    public function prayer(Request $request): RedirectResponse
    {
        $person = $this->person($request);
        $data = $request->validate(['prayer_text' => ['required', 'string', 'max:4000'], 'visibility' => ['required', Rule::in(['prayer_team', 'assigned_care_only'])], 'consent_to_contact' => ['nullable', 'boolean']]);
        DB::table('prayer_requests')->insert(['id' => (string) Str::uuid(), 'person_id' => $person->id, 'user_id' => $request->user()->id, 'prayer_text_encrypted' => Crypt::encryptString(trim($data['prayer_text'])), 'visibility' => $data['visibility'], 'consent_to_contact' => $request->boolean('consent_to_contact'), 'status' => 'received', 'created_at' => now(), 'updated_at' => now()]);

        return to_route('member.home')->withFragment('prayer')->with('status', 'Your private prayer request was received.');
    }

    public function journey(Request $request): RedirectResponse
    {
        $person = $this->person($request);
        $data = $request->validate(['type' => ['required', Rule::in(['membership', 'baptism', 'new_member_class', 'discipleship', 'serving'])], 'consent_to_contact' => ['accepted']]);
        $duplicate = DB::table('member_journeys')->where('person_id', $person->id)->where('type', $data['type'])->whereIn('status', ['requested', 'contacted', 'enrolled'])->exists();
        if ($duplicate) {
            return to_route('member.home')->withFragment('next-steps')->withErrors(['type' => 'You already have an active request for this next step.']);
        }
        $journeyId = (string) Str::uuid();
        $taskId = (string) Str::uuid();
        DB::transaction(function () use ($person, $request, $data, $journeyId, $taskId): void {
            DB::table('follow_up_tasks')->insert(['id' => $taskId, 'person_id' => $person->id, 'kind' => 'journey_'.$data['type'], 'status' => 'open', 'summary' => 'Member requested '.str($data['type'])->replace('_', ' ')->headline().' follow-up.', 'due_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('member_journeys')->insert(['id' => $journeyId, 'person_id' => $person->id, 'user_id' => $request->user()->id, 'follow_up_task_id' => $taskId, 'type' => $data['type'], 'status' => 'requested', 'consent_to_contact' => true, 'created_at' => now(), 'updated_at' => now()]);
        });

        return to_route('member.home')->withFragment('next-steps')->with('status', 'Your next-step request was sent to the ministry team.');
    }

    public function update(Request $request): RedirectResponse
    {
        $person = $this->person($request);
        $profile = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'consent_to_contact' => ['nullable', 'boolean'],
            'birthday_collection_consent' => ['nullable', 'boolean'],
            'birthday_month' => ['nullable', 'integer', 'between:1,12', 'required_if:birthday_collection_consent,1'],
            'birthday_day' => ['nullable', 'integer', 'between:1,31', 'required_if:birthday_collection_consent,1'],
        ]);
        $birthdayConsent = $request->boolean('birthday_collection_consent');
        $birthday = null;
        if ($birthdayConsent) {
            $month = (int) $profile['birthday_month'];
            $day = (int) $profile['birthday_day'];
            if (! checkdate($month, $day, 2000)) {
                throw ValidationException::withMessages(['birthday_day' => 'Choose a valid day for the selected month.']);
            }
            // A leap year is deliberately used so the record contains only month and day, never an age.
            $birthday = CarbonImmutable::create(2000, $month, $day, 0, 0, 0, 'UTC')->toDateString();
        }
        unset($profile['birthday_collection_consent'], $profile['birthday_month'], $profile['birthday_day']);
        $profile['consent_to_contact'] = $request->boolean('consent_to_contact');
        $profile['birthday'] = $birthday;

        $before = DB::table('people')->where('id', $person->id)->first();
        $changedFields = collect($profile)->filter(fn ($value, $field) => (string) ($before->{$field} ?? '') !== (string) ($value ?? ''))->keys()->values()->all();
        DB::table('people')->where('id', $person->id)->update([...$profile, 'updated_at' => now()]);
        $preferenceValues = [
            'email_marketing' => $request->boolean('email_marketing'),
            'sms_marketing' => $request->boolean('sms_marketing'),
            'birthday_staff_reminder' => $birthdayConsent && $request->boolean('birthday_staff_reminder'),
            'birthday_public_recognition' => $birthdayConsent && $request->boolean('birthday_public_recognition'),
            'updated_at' => now(),
        ];
        if (DB::table('notification_preferences')->where('person_id', $person->id)->exists()) {
            DB::table('notification_preferences')->where('person_id', $person->id)->update($preferenceValues);
        } else {
            DB::table('notification_preferences')->insert([...$preferenceValues, 'id' => (string) Str::uuid(), 'person_id' => $person->id, 'created_at' => now()]);
        }
        if ($changedFields !== []) {
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id,
                'action' => 'people.member_profile_updated', 'subject_type' => 'people', 'subject_id' => $person->id,
                'changes_json' => json_encode(['fields' => $changedFields]), 'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        }

        return to_route('member.home')->with('status', 'Your profile and preferences were saved.');
    }

    private function person(Request $request): object
    {
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        if ($person) {
            return $person;
        }
        $id = (string) Str::uuid();
        $names = preg_split('/\s+/', trim($request->user()->name), 2);
        DB::table('people')->insert(['id' => $id, 'user_id' => $request->user()->id, 'first_name' => $names[0] ?: 'Member', 'last_name' => $names[1] ?? null, 'email' => $request->user()->email, 'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('people')->where('id', $id)->first();
    }
}
