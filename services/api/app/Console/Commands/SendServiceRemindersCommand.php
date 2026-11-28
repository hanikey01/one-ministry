<?php

namespace App\Console\Commands;

use App\Services\EmailTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SendServiceRemindersCommand extends Command
{
    protected $signature = 'services:send-reminders {--limit=100 : Maximum subscriptions to evaluate}';

    protected $description = 'Send idempotent member-requested service reminder emails';

    public function handle(EmailTemplateService $templates): int
    {
        $now = CarbonImmutable::now('UTC');
        $limit = max(1, min(500, (int) $this->option('limit')));
        $subscriptions = DB::table('service_reminder_subscriptions')
            ->join('people', 'people.id', '=', 'service_reminder_subscriptions.person_id')
            ->leftJoin('notification_preferences', 'notification_preferences.person_id', '=', 'people.id')
            ->where('service_reminder_subscriptions.active', true)
            ->where('service_reminder_subscriptions.channel', 'email')
            ->whereNotNull('people.email')
            ->orderBy('service_reminder_subscriptions.created_at')
            ->limit($limit)
            ->get([
                'service_reminder_subscriptions.*', 'people.first_name', 'people.preferred_name', 'people.last_name', 'people.email',
                'notification_preferences.quiet_hours_timezone', 'notification_preferences.quiet_hours_start', 'notification_preferences.quiet_hours_end',
            ]);
        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $occurrence = $this->dueOccurrence($subscription, $now);
            if (! $occurrence || $this->insideQuietHours($subscription, $now)) {
                continue;
            }
            $delivery = DB::table('service_reminder_deliveries')
                ->where('subscription_id', $subscription->id)
                ->where('service_occurrence_id', $occurrence->id)
                ->first();
            if ($delivery?->status === 'sent') {
                continue;
            }

            $deliveryId = $delivery?->id ?? (string) Str::uuid();
            $scheduledFor = CarbonImmutable::parse($occurrence->starts_at, 'UTC')->subMinutes((int) $subscription->lead_minutes);
            DB::table('service_reminder_deliveries')->updateOrInsert(['id' => $deliveryId], [
                'subscription_id' => $subscription->id,
                'service_occurrence_id' => $occurrence->id,
                'scheduled_for' => $scheduledFor,
                'status' => 'pending',
                'attempts' => (int) ($delivery?->attempts ?? 0) + 1,
                'failure_code' => null,
                'last_attempt_at' => now(),
                'created_at' => $delivery?->created_at ?? now(),
                'updated_at' => now(),
            ]);

            try {
                $memberTimezone = $subscription->timezone ?: $subscription->quiet_hours_timezone ?: $occurrence->timezone;
                $start = CarbonImmutable::parse($occurrence->starts_at, 'UTC');
                $memberName = trim(($subscription->preferred_name ?: $subscription->first_name).' '.($subscription->last_name ?? ''));
                $templates->send('service_reminder', $subscription->email, [
                    'member_name' => $memberName,
                    'service_name' => $occurrence->title,
                    'service_date' => $start->setTimezone($memberTimezone)->format('l, F j \a\t g:i A').' · '.str_replace('_', ' ', $memberTimezone),
                    'ministry_time' => $start->setTimezone($occurrence->timezone)->format('l, F j \a\t g:i A').' · '.str_replace('_', ' ', $occurrence->timezone),
                    'location_name' => $occurrence->location_name ?: 'See the service-times page for details',
                    'action_url' => route('public.service-times'),
                ], $memberName, 'View service times');
                DB::table('service_reminder_deliveries')->where('id', $deliveryId)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
                if ($subscription->service_occurrence_id) {
                    DB::table('service_reminder_subscriptions')->where('id', $subscription->id)->update(['active' => false, 'updated_at' => now()]);
                }
                $sent++;
            } catch (\Throwable $error) {
                $failureCode = Str::limit(class_basename($error), 120, '');
                DB::table('service_reminder_deliveries')->where('id', $deliveryId)->update(['status' => 'failed', 'failure_code' => $failureCode, 'updated_at' => now()]);
                Log::warning('Service reminder delivery failed.', ['delivery_id' => $deliveryId, 'failure_code' => $failureCode]);
            }
        }

        $this->line("Sent {$sent} service reminder email(s).");

        return self::SUCCESS;
    }

    private function dueOccurrence(object $subscription, CarbonImmutable $now): ?object
    {
        $query = DB::table('service_occurrences')
            ->leftJoin('locations', 'locations.id', '=', 'service_occurrences.location_id')
            ->where('service_occurrences.rehearsal_mode', false)
            ->whereIn('service_occurrences.state', ['scheduled', 'ready'])
            ->where('service_occurrences.starts_at', '>', $now)
            ->where('service_occurrences.starts_at', '<=', $now->addMinutes((int) $subscription->lead_minutes));
        if ($subscription->service_occurrence_id) {
            $query->where('service_occurrences.id', $subscription->service_occurrence_id);
        } else {
            $query->where('service_occurrences.service_schedule_id', $subscription->service_schedule_id);
        }

        return $query->orderBy('service_occurrences.starts_at')->first([
            'service_occurrences.id', 'service_occurrences.title', 'service_occurrences.starts_at', 'service_occurrences.timezone', 'locations.name as location_name',
        ]);
    }

    private function insideQuietHours(object $subscription, CarbonImmutable $now): bool
    {
        if (! $subscription->quiet_hours_timezone || ! $subscription->quiet_hours_start || ! $subscription->quiet_hours_end) {
            return false;
        }
        $local = $now->setTimezone($subscription->quiet_hours_timezone);
        $start = CarbonImmutable::parse($local->toDateString().' '.$subscription->quiet_hours_start, $subscription->quiet_hours_timezone);
        $end = CarbonImmutable::parse($local->toDateString().' '.$subscription->quiet_hours_end, $subscription->quiet_hours_timezone);
        if ($end->lessThanOrEqualTo($start)) {
            return $local->greaterThanOrEqualTo($start) || $local->lessThan($end);
        }

        return $local->betweenIncluded($start, $end);
    }
}
