<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\ConnectionRequestService;
use App\Services\DepartmentService;
use App\Services\ModuleAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function departments(Request $request): View
    {
        return $this->page($request, 'member.departments', 'My departments');
    }

    public function department(Request $request, string $department): View
    {
        $person = $this->person($request);
        $item = DB::table('departments')->join('department_memberships', 'department_memberships.department_id', '=', 'departments.id')
            ->where('departments.id', $department)->where('department_memberships.person_id', $person->id)->where('department_memberships.status', 'active')
            ->select('departments.*', 'department_memberships.role')->first();
        abort_unless($item, 404);
        $messages = $this->departmentMessageRows($department);
        DB::table('department_message_reads')->updateOrInsert(['department_id' => $department, 'person_id' => $person->id], ['last_read_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return view('member.department', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'features' => DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all(), 'user' => $request->user(), 'person' => $person, 'department' => $item, 'messages' => $messages]);
    }

    public function departmentMessages(Request $request, string $department): JsonResponse
    {
        $person = $this->person($request);
        abort_unless(DB::table('department_memberships')->where(['department_id' => $department, 'person_id' => $person->id, 'status' => 'active'])->exists(), 404);
        DB::table('department_message_reads')->updateOrInsert(['department_id' => $department, 'person_id' => $person->id], ['last_read_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $messages = $this->departmentMessageRows($department);

        return response()->json(['html' => view('member.partials.department-messages', compact('messages', 'person', 'department'))->render(), 'latest_id' => $messages->last()?->id]);
    }

    public function requestDepartment(Request $request, string $department, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
        $departments->requestMembership($department, $request->user(), $data['message'] ?? null, $request->ip());

        return to_route('member.departments')->with('status', 'Your request was sent to the department leaders.');
    }

    public function withdrawDepartmentRequest(Request $request, string $department, DepartmentService $departments): RedirectResponse
    {
        $departments->withdrawRequest($department, $request->user(), $request->ip());

        return back()->with('status', 'Join request withdrawn.');
    }

    public function leaveDepartment(Request $request, string $department, DepartmentService $departments): RedirectResponse
    {
        $departments->leave($department, $request->user(), $request->ip());

        return to_route('member.departments')->with('status', 'You left the department.');
    }

    public function postDepartmentMessage(Request $request, string $department, DepartmentService $departments): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000'], 'parent_message_id' => ['nullable', 'uuid']]);
        $departments->postMessage($department, $request->user(), $data['content'], $request->ip(), $data['parent_message_id'] ?? null);

        if ($request->expectsJson()) return response()->json(['message' => 'Message sent.'], 201);

        return to_route('member.departments.show', $department)->with('status', 'Message posted.');
    }

    public function updateDepartmentMessage(Request $request, string $department, string $message, DepartmentService $departments): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $departments->updateOwnMessage($department, $message, $request->user(), $data['content'], $request->ip());

        if ($request->expectsJson()) return response()->json(['message' => 'Message updated.']);

        return back()->with('status', 'Message updated.');
    }

    public function deleteDepartmentMessage(Request $request, string $department, string $message, DepartmentService $departments): RedirectResponse|JsonResponse
    {
        $departments->deleteOwnMessage($department, $message, $request->user(), $request->ip());

        if ($request->expectsJson()) return response()->json(['message' => 'Message deleted.']);

        return back()->with('status', 'Message deleted.');
    }

    private function departmentMessageRows(string $department)
    {
        return DB::table('department_messages')->join('people', 'people.id', '=', 'department_messages.person_id')
            ->leftJoin('department_messages as parent', 'parent.id', '=', 'department_messages.parent_message_id')
            ->where('department_messages.department_id', $department)->whereNull('department_messages.moderated_at')
            ->latest('department_messages.created_at')->limit(100)
            ->get(['department_messages.id', 'department_messages.person_id', 'department_messages.parent_message_id', 'department_messages.content', 'department_messages.edited_at', 'department_messages.created_at', 'people.first_name', 'people.last_name', 'parent.content as parent_content'])
            ->reverse()->values();
    }

    public function activity(Request $request): View
    {
        abort_unless(app(ModuleAvailability::class)->enabled('attendance'), 404);

        return $this->page($request, 'member.activity', 'My participation');
    }

    public function nextSteps(Request $request): View
    {
        if (! $request->user()) {
            return $this->guestConnectionPage('member.next-steps', 'Next steps');
        }

        return $this->page($request, 'member.next-steps', 'My next steps');
    }

    public function prayerPage(Request $request): View
    {
        if (! $request->user()) {
            return $this->guestConnectionPage('member.prayer', 'Prayer');
        }

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
        $isHome = $view === 'member.home';
        $departments = ($isHome || $view === 'member.departments')
            ? DB::table('department_memberships')->join('departments', 'departments.id', '=', 'department_memberships.department_id')->leftJoin('department_message_reads', fn ($join) => $join->on('department_message_reads.department_id', '=', 'departments.id')->on('department_message_reads.person_id', '=', 'department_memberships.person_id'))->where('department_memberships.person_id', $person->id)->where('department_memberships.status', 'active')->select('departments.id', 'departments.name', 'departments.description', 'departments.image_url', 'departments.category', 'department_memberships.role')->selectRaw('(select count(*) from department_messages where department_messages.department_id=departments.id and department_messages.moderated_at is null and department_messages.person_id<>? and department_messages.created_at>coalesce(department_message_reads.last_read_at, department_memberships.created_at)) as unread_count', [$person->id])->get()
            : collect();
        $pendingDepartments = $view === 'member.departments' ? DB::table('department_join_requests')->join('departments', 'departments.id', '=', 'department_join_requests.department_id')->where('person_id', $person->id)->where('department_join_requests.status', 'pending')->get(['departments.id', 'departments.name', 'departments.image_url', 'department_join_requests.created_at']) : collect();
        $events = ($isHome || $view === 'member.events')
            ? DB::table('event_registrations')->join('events', 'events.id', '=', 'event_registrations.event_id')->where('person_id', $person->id)->whereIn('event_registrations.status', ['registered', 'waitlisted', 'checked_in'])->where('events.starts_at', '>=', now())->orderBy('events.starts_at')->get(['events.id', 'events.slug', 'events.title', 'events.image_url', 'events.starts_at', 'events.location', 'events.cancelled_at', 'event_registrations.status as registration_status'])
            : collect();
        $donations = ($isHome || $view === 'member.giving') && in_array('giving', $features, true)
            ? DB::table('donations')->join('giving_funds', 'giving_funds.id', '=', 'donations.fund_id')->where('donations.person_id', $person->id)->whereIn('donations.status', ['confirmed', 'refunded', 'reversed'])->latest('donations.confirmed_at')->limit(10)->get(['donations.amount_minor', 'donations.currency', 'donations.status', 'donations.confirmed_at', 'giving_funds.name as fund_name'])
            : collect();
        $attendance = app(ModuleAvailability::class)->enabled('attendance') && ($isHome || $view === 'member.activity')
            ? DB::table('attendance_records')->where('person_id', $person->id)->whereIn('status', ['present', 'confirmed', 'self_reported', 'corrected'])->latest('observed_at')->limit(6)->get(['source', 'status', 'observed_at'])
            : collect();
        $journeyOptions = ($isHome || $view === 'member.next-steps')
            ? DB::table('next_step_options')->where('active', true)->orderBy('display_order')->orderBy('label')->get(['key', 'label', 'description'])
            : collect();
        $prayers = ($isHome || $view === 'member.prayer')
            ? DB::table('prayer_requests')->where('person_id', $person->id)->latest('created_at')->get(['id', 'visibility', 'status', 'created_at'])
            : collect();

        return view($view, [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'person' => $person,
            'preferences' => $view === 'member.profile' ? DB::table('notification_preferences')->where('person_id', $person->id)->first() : null,
            'birthday' => $view === 'member.profile' && $person->birthday ? CarbonImmutable::parse($person->birthday) : null,
            'departments' => $departments, 'pendingDepartments' => $pendingDepartments, 'events' => $events, 'donations' => $donations,
            'attendance' => $attendance, 'journeyOptions' => $journeyOptions, 'prayers' => $prayers,
            'nextEvent' => $events->first(),
            'features' => $features,
            'metrics' => ['departments' => $departments->count(), 'events' => $events->count(), 'journeys' => DB::table('follow_up_tasks')->where('person_id', $person->id)->where('kind', 'next_step')->whereIn('status', ['open', 'in_progress'])->count()],
            'title' => $title,
        ]);
    }

    public function prayer(Request $request, ConnectionRequestService $connections): RedirectResponse
    {
        $data = $this->connectionData($request, [
            'prayer_text' => ['required', 'string', 'max:4000'],
            'visibility' => ['required', Rule::in(['prayer_team', 'assigned_care_only'])],
            'consent_to_contact' => ['nullable', 'boolean'],
        ]);
        $connections->rejectBot($data);
        $connections->submitPrayer($request, $connections->person($request, $data), $data);

        return to_route('member.prayer.page')->with('status', 'Your private prayer request was received.');
    }

    public function requestNextStep(Request $request, ConnectionRequestService $connections): RedirectResponse
    {
        $data = $this->connectionData($request, [
            'type' => ['required', Rule::exists('next_step_options', 'key')->where(fn ($query) => $query->where('active', true))],
            'consent_to_contact' => ['accepted'],
        ]);
        $connections->rejectBot($data);
        if (! $connections->submitNextStep($request, $connections->person($request, $data), $data['type'])) {
            return to_route('member.next-steps')->withErrors(['type' => 'You already have an active request for this next step.']);
        }

        return to_route('member.next-steps')->with('status', 'Your next-step request was sent to the ministry team.');
    }

    private function guestConnectionPage(string $view, string $title): View
    {
        $features = DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all();

        return view($view, [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'features' => $features,
            'title' => $title,
            'guest' => true,
            'journeyOptions' => $view === 'member.next-steps'
                ? DB::table('next_step_options')->where('active', true)->orderBy('display_order')->orderBy('label')->get(['key', 'label', 'description'])
                : collect(),
            'prayers' => collect(),
        ]);
    }

    /** @param array<string, array<int, mixed>> $rules */
    private function connectionData(Request $request, array $rules): array
    {
        if (! $request->user()) {
            $rules += [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['nullable', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:254'],
                'phone' => ['nullable', 'string', 'max:40'],
            ];
        }
        $rules['website'] = ['nullable', 'string', 'max:0'];

        return $request->validate($rules);
    }

    public function update(Request $request): RedirectResponse
    {
        $person = $this->person($request);
        $previousProfileImagePath = $person->profile_image_path;
        $newProfileImagePath = null;
        $profile = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'consent_to_contact' => ['nullable', 'boolean'],
            'birthday_month' => ['required', 'integer', 'between:1,12'],
            'birthday_day' => ['required', 'integer', 'between:1,31'],
            'profile_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if ($request->hasFile('profile_image')) {
            $newProfileImagePath = $request->file('profile_image')->store('profiles', 'public');
            if (! is_string($newProfileImagePath) || ! Storage::disk('public')->exists($newProfileImagePath)) {
                throw ValidationException::withMessages(['profile_image' => 'The profile photo could not be stored. Please try again.']);
            }
            $profile['profile_image_path'] = $newProfileImagePath;
        }
        unset($profile['profile_image']);
        $month = (int) $profile['birthday_month'];
        $day = (int) $profile['birthday_day'];
        if (! checkdate($month, $day, 2000)) {
            throw ValidationException::withMessages(['birthday_day' => 'Choose a valid day for the selected month.']);
        }
        // A leap year is deliberately used so the record contains only month and day, never an age.
        $birthday = CarbonImmutable::create(2000, $month, $day, 0, 0, 0, 'UTC')->toDateString();
        unset($profile['birthday_month'], $profile['birthday_day']);
        $profile['consent_to_contact'] = $request->boolean('consent_to_contact');
        $profile['birthday'] = $birthday;

        $before = DB::table('people')->where('id', $person->id)->first();
        $changedFields = collect($profile)->filter(fn ($value, $field) => (string) ($before->{$field} ?? '') !== (string) ($value ?? ''))->keys()->values()->all();
        DB::table('people')->where('id', $person->id)->update([...$profile, 'updated_at' => now()]);
        if ($newProfileImagePath && $previousProfileImagePath && $previousProfileImagePath !== $newProfileImagePath) {
            Storage::disk('public')->delete($previousProfileImagePath);
        }
        $preferenceValues = [
            'email_marketing' => $request->boolean('email_marketing'),
            'sms_marketing' => $request->boolean('sms_marketing'),
            'birthday_staff_reminder' => $request->boolean('birthday_staff_reminder'),
            'birthday_public_recognition' => $request->boolean('birthday_public_recognition'),
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

        return to_route('member.profile')->with('status', 'Your profile and preferences were saved.');
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
