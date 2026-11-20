<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\EmailDeliverySettingsService;
use App\Services\ModuleAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StaffPeopleController extends Controller
{
    public function contact(Request $r, EmailDeliverySettingsService $delivery): JsonResponse|RedirectResponse
    {
        $d = $r->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'], 'message' => ['nullable', 'string', 'max:2000'], 'consent_to_contact' => ['accepted']]);
        $message = filled($d['message'] ?? null) ? trim($d['message']) : null;
        unset($d['message']);
        $id = (string) Str::uuid();
        DB::table('people')->insert(array_merge($d, ['id' => $id, 'status' => 'member', 'consent_to_contact' => true, 'created_at' => now(), 'updated_at' => now()]));
        $task = (string) Str::uuid();
        DB::table('follow_up_tasks')->insert(['id' => $task, 'person_id' => $id, 'kind' => 'contact_follow_up', 'summary' => $message ? 'Contact request: '.str($message)->limit(190) : 'Follow up with new contact', 'created_at' => now(), 'updated_at' => now()]);

        $recipient = MinistrySetting::query()->value('contact_email');
        if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) && $delivery->active()) {
            $name = trim(($d['first_name'] ?? '').' '.($d['last_name'] ?? ''));
            $subject = 'New website contact request from '.($name !== '' ? $name : 'a visitor');
            $lines = [
                'A new contact request was submitted through the ministry website.',
                '', 'Name: '.($name !== '' ? $name : 'Not provided'),
                'Email: '.($d['email'] ?? 'Not provided'),
                'Phone: '.($d['phone'] ?? 'Not provided'),
                '', 'Message:', $message ?: 'No message provided.',
                '', 'The request is also available in People and Follow-up.',
            ];
            $text = implode("\n", $lines);
            $html = '<p>A new contact request was submitted through the ministry website.</p>'
                .'<p><strong>Name:</strong> '.e($name !== '' ? $name : 'Not provided').'<br>'
                .'<strong>Email:</strong> '.e($d['email'] ?? 'Not provided').'<br>'
                .'<strong>Phone:</strong> '.e($d['phone'] ?? 'Not provided').'</p>'
                .'<p><strong>Message</strong><br>'.nl2br(e($message ?: 'No message provided.')).'</p>'
                .'<p>The request is also available in People and Follow-up.</p>';
            try {
                $delivery->sendHtml($recipient, $subject, $html, $text);
            } catch (Throwable $exception) {
                Log::warning('Contact request email delivery failed after the request was safely recorded.', ['person_id' => $id, 'exception' => $exception::class]);
            }
        }

        if (! $r->expectsJson()) {
            $previous = url()->previous() ?: url('/');

            return redirect()->to($previous.(str_contains($previous, '?') ? '&' : '?').'contact=received');
        }

        return response()->json(['data' => ['person_id' => $id, 'follow_up_task_id' => $task, 'status' => 'received']], 201);
    }

    public function people(): JsonResponse
    {
        return response()->json(['data' => DB::table('people')->latest()->paginate(50)]);
    }

    public function duplicates(): JsonResponse
    {
        $email = DB::table('people')->select('email', DB::raw('count(*) as matches'), DB::raw("string_agg(id::text, ',') as person_ids"))->whereNotNull('email')->groupBy('email')->havingRaw('count(*) > 1')->get()->map(fn ($row) => ['field' => 'email', 'value' => $row->email, 'matches' => $row->matches, 'person_ids' => explode(',', $row->person_ids)]);
        $phone = DB::table('people')->select('phone', DB::raw('count(*) as matches'), DB::raw("string_agg(id::text, ',') as person_ids"))->whereNotNull('phone')->groupBy('phone')->havingRaw('count(*) > 1')->get()->map(fn ($row) => ['field' => 'phone', 'value' => $row->phone, 'matches' => $row->matches, 'person_ids' => explode(',', $row->person_ids)]);

        return response()->json(['data' => $email->concat($phone)->values()]);
    }

    public function profile(Request $request, string $person): JsonResponse
    {
        $record = DB::table('people')->where('id', $person)->first();
        abort_unless($record, 404);

        $data = ['person' => $record, 'departments' => DB::table('department_memberships')->join('departments', 'departments.id', '=', 'department_memberships.department_id')->where('department_memberships.person_id', $person)->where('department_memberships.status', 'active')->get(['departments.id', 'departments.name', 'department_memberships.role']), 'follow_up' => DB::table('follow_up_tasks')->where('person_id', $person)->latest()->limit(25)->get(['id', 'kind', 'status', 'summary', 'due_at', 'completed_at', 'created_at'])];
        if (app(ModuleAvailability::class)->visibleToStaff('attendance', $request->user())) {
            $data['attendance'] = DB::table('attendance_records')->join('service_occurrences', 'service_occurrences.id', '=', 'attendance_records.service_occurrence_id')->where('attendance_records.person_id', $person)->latest('attendance_records.observed_at')->limit(25)->get(['attendance_records.id', 'attendance_records.source', 'attendance_records.status', 'attendance_records.observed_at', 'service_occurrences.title']);
        }

        return response()->json(['data' => $data]);
    }

    public function updatePerson(Request $r, string $person): JsonResponse
    {
        $data = $r->validate(['first_name' => ['sometimes', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'], 'birthday' => ['nullable', 'date']]);
        $query = DB::table('people')->where('id', $person);
        $before = $query->first();
        abort_unless($before, 404);
        $changes = [];
        foreach ($data as $key => $value) {
            if ($before->{$key} != $value) {
                $changes[$key] = ['from' => $before->{$key}, 'to' => $value];
            }
        }if ($changes) {
            $query->update([...$data, 'updated_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $r->user()->id, 'action' => 'people.person_updated', 'subject_type' => 'people', 'subject_id' => $person, 'changes_json' => json_encode($changes), 'ip_address' => $r->ip(), 'created_at' => now()]);
        }

        return response()->json(['data' => $query->first()]);
    }

    public function createPerson(Request $r): JsonResponse
    {
        $d = $r->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'], 'consent_to_contact' => ['boolean']]);
        $id = (string) Str::uuid();
        DB::table('people')->insert(array_merge($d, ['id' => $id, 'status' => 'member', 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['data' => DB::table('people')->where('id', $id)->first()], 201);
    }

    public function attendance(Request $r): JsonResponse
    {
        $d = $r->validate(['person_id' => ['required', 'uuid', 'exists:people,id'], 'source' => ['sometimes', 'in:identified_presence,in_person_checkin,staff_correction'], 'status' => ['sometimes', 'in:present,absent']]);
        $id = (string) Str::uuid();
        DB::table('attendance_records')->updateOrInsert(['service_occurrence_id' => $r->route('service')->id, 'person_id' => $d['person_id']], array_merge($d, ['id' => $id, 'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['data' => DB::table('attendance_records')->where('service_occurrence_id', $r->route('service')->id)->where('person_id', $d['person_id'])->first()], 201);
    }

    public function tasks(): JsonResponse
    {
        $tasks = DB::table('follow_up_tasks')->leftJoin('people', 'people.id', '=', 'follow_up_tasks.person_id')->leftJoin('users as assignees', 'assignees.id', '=', 'follow_up_tasks.assigned_to')->where('follow_up_tasks.status', '!=', 'completed')->orderByRaw('follow_up_tasks.due_at is null')->orderBy('follow_up_tasks.due_at')->select('follow_up_tasks.*', 'people.first_name', 'people.last_name', 'people.email as person_email', 'assignees.name as assignee_name')->paginate(50);
        $now = now();
        $tasks->getCollection()->transform(function ($task) use ($now) {
            $due = $task->due_at ? CarbonImmutable::parse($task->due_at) : null;
            $task->triage = $due === null ? 'unscheduled' : ($due->lt($now->startOfDay()) ? 'overdue' : ($due->isSameDay($now) ? 'due_today' : 'upcoming'));

            return $task;
        });

        return response()->json(['data' => $tasks]);
    }

    public function birthdays(): JsonResponse
    {
        $days = request()->integer('days', 30);
        $days = max(1, min($days, 90));
        $today = now()->startOfDay();
        $people = DB::table('people')->join('notification_preferences', 'notification_preferences.person_id', '=', 'people.id')->where('notification_preferences.birthday_staff_reminder', true)->whereNotNull('people.birthday')->select('people.id', 'people.first_name', 'people.last_name', 'people.birthday', 'people.email', 'people.phone')->get()->map(function ($person) use ($today) {
            $birthday = CarbonImmutable::parse($person->birthday);
            $next = $birthday->setYear($today->year);
            if ($next->lt($today)) {
                $next = $next->addYear();
            }$person->next_birthday = $next->toDateString();
            $person->days_until = $today->diffInDays($next);

            return $person;
        })->filter(fn ($person) => $person->days_until <= $days)->sortBy('days_until')->values();

        return response()->json(['data' => $people]);
    }

    public function createTask(Request $r): JsonResponse
    {
        $d = $r->validate(['person_id' => ['nullable', 'uuid', 'exists:people,id'], 'service_occurrence_id' => ['nullable', 'uuid', 'exists:service_occurrences,id'], 'kind' => ['required', 'string', 'max:40'], 'summary' => ['required', 'string', 'max:2000'], 'due_at' => ['nullable', 'date']]);
        $id = (string) Str::uuid();
        DB::table('follow_up_tasks')->insert(array_merge($d, ['id' => $id, 'created_at' => now(), 'updated_at' => now()]));

        return response()->json(['data' => DB::table('follow_up_tasks')->where('id', $id)->first()], 201);
    }
}
