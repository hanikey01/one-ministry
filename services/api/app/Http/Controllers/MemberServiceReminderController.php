<?php

namespace App\Http\Controllers;

use App\Services\EmailDeliverySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MemberServiceReminderController extends Controller
{
    public function store(Request $request, EmailDeliverySettingsService $delivery): RedirectResponse
    {
        $data = $request->validate([
            'service_occurrence_id' => ['required', 'uuid', 'exists:service_occurrences,id'],
            'scope' => ['required', Rule::in(['occurrence', 'schedule'])],
            'lead_minutes' => ['required', Rule::in([60, 1440])],
            'timezone' => ['nullable', 'timezone'],
        ]);
        if (! $delivery->active()) {
            throw ValidationException::withMessages(['service_occurrence_id' => 'Email reminders are not available until the ministry activates email delivery.']);
        }
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        if (! $person || ! $person->email) {
            throw ValidationException::withMessages(['service_occurrence_id' => 'Add an email address to your member profile before enabling reminders.']);
        }

        $occurrence = DB::table('service_occurrences')
            ->where('id', $data['service_occurrence_id'])
            ->where('rehearsal_mode', false)
            ->whereIn('state', ['scheduled', 'ready'])
            ->where('starts_at', '>', now())
            ->first(['id', 'service_schedule_id']);
        if (! $occurrence) {
            throw ValidationException::withMessages(['service_occurrence_id' => 'This service is no longer available for reminders.']);
        }
        if ($data['scope'] === 'schedule' && ! $occurrence->service_schedule_id) {
            throw ValidationException::withMessages(['scope' => 'This is a one-time service and cannot be followed as a recurring schedule.']);
        }

        $keys = [
            'person_id' => $person->id,
            'service_occurrence_id' => $data['scope'] === 'occurrence' ? $occurrence->id : null,
            'service_schedule_id' => $data['scope'] === 'schedule' ? $occurrence->service_schedule_id : null,
            'channel' => 'email',
            'lead_minutes' => (int) $data['lead_minutes'],
        ];
        $existing = DB::table('service_reminder_subscriptions')->where($keys)->first();
        $values = ['timezone' => $data['timezone'] ?: null, 'active' => true, 'cancelled_at' => null, 'updated_at' => now()];
        if ($existing) {
            DB::table('service_reminder_subscriptions')->where('id', $existing->id)->update($values);
        } else {
            DB::table('service_reminder_subscriptions')->insert([...$keys, ...$values, 'id' => (string) Str::uuid(), 'created_at' => now()]);
        }

        return back()->with('status', 'Your service reminder is active.');
    }

    public function destroy(Request $request, string $subscription): RedirectResponse
    {
        $personId = DB::table('people')->where('user_id', $request->user()->id)->value('id');
        abort_unless($personId, 404);
        $updated = DB::table('service_reminder_subscriptions')
            ->where('id', $subscription)
            ->where('person_id', $personId)
            ->update(['active' => false, 'cancelled_at' => now(), 'updated_at' => now()]);
        abort_unless($updated, 404);

        return back()->with('status', 'The service reminder was cancelled.');
    }
}
