<?php

namespace App\Console\Commands;

use App\Models\MinistrySetting;
use App\Services\EmailTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SendBirthdayRemindersCommand extends Command
{
    protected $signature = 'birthdays:send-reminders {--days=1 : Days before the birthday}';
    protected $description = 'Send idempotent advance birthday reminders to authorized active staff';

    public function handle(EmailTemplateService $templates): int
    {
        $days = max(1, min(14, (int) $this->option('days')));
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $today = CarbonImmutable::now($settings->timezone)->startOfDay();
        $birthdayDate = $today->addDays($days);
        $recipients = $this->recipients();
        $processed = 0;

        $people = DB::table('people')
            ->whereNotNull('birthday')
            ->whereNotIn('status', ['inactive', 'archived', 'deleted'])
            ->get(['id', 'first_name', 'preferred_name', 'last_name', 'birthday']);

        foreach ($people as $person) {
            $stored = CarbonImmutable::parse($person->birthday, 'UTC');
            if (! $this->matches($stored, $birthdayDate)) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $delivery = DB::table('birthday_reminder_deliveries')
                    ->where('person_id', $person->id)
                    ->where('recipient_user_id', $recipient->id)
                    ->where('birthday_year', $birthdayDate->year)
                    ->where('reminder_type', 'staff_advance')
                    ->first();
                if ($delivery?->status === 'sent') {
                    continue;
                }

                $deliveryId = $delivery?->id ?? (string) Str::uuid();
                DB::table('birthday_reminder_deliveries')->updateOrInsert(
                    ['id' => $deliveryId],
                    [
                        'person_id' => $person->id,
                        'recipient_user_id' => $recipient->id,
                        'birthday_year' => $birthdayDate->year,
                        'reminder_type' => 'staff_advance',
                        'status' => 'pending',
                        'attempts' => (int) ($delivery?->attempts ?? 0) + 1,
                        'failure_code' => null,
                        'last_attempt_at' => now(),
                        'created_at' => $delivery?->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );

                try {
                    $name = trim(($person->preferred_name ?: $person->first_name).' '.($person->last_name ?? ''));
                    $templates->send('birthday_staff_reminder', $recipient->email, [
                        'staff_name' => $recipient->name,
                        'member_name' => $name,
                        'birthday_date' => $birthdayDate->format('F j'),
                    ], $recipient->name);
                    DB::table('birthday_reminder_deliveries')->where('id', $deliveryId)->update([
                        'status' => 'sent', 'sent_at' => now(), 'updated_at' => now(),
                    ]);
                    $processed++;
                } catch (\Throwable $error) {
                    $failureCode = class_basename($error);
                    DB::table('birthday_reminder_deliveries')->where('id', $deliveryId)->update([
                        'status' => 'failed', 'failure_code' => Str::limit($failureCode, 120, ''), 'updated_at' => now(),
                    ]);
                    Log::warning('Birthday reminder delivery failed.', [
                        'delivery_id' => $deliveryId,
                        'failure_code' => $failureCode,
                    ]);
                }
            }
        }

        $this->line("Sent {$processed} birthday reminder email(s).");

        return self::SUCCESS;
    }

    private function matches(CarbonImmutable $stored, CarbonImmutable $target): bool
    {
        if ($stored->month === 2 && $stored->day === 29 && ! $target->isLeapYear()) {
            return $target->month === 2 && $target->day === 28;
        }

        return $stored->month === $target->month && $stored->day === $target->day;
    }

    private function recipients(): \Illuminate\Support\Collection
    {
        return DB::table('users')
            ->join('user_role_assignments', 'user_role_assignments.user_id', '=', 'users.id')
            ->leftJoin('role_permissions', 'role_permissions.role_key', '=', 'user_role_assignments.role_key')
            ->whereNotNull('users.email_verified_at')
            ->where('user_role_assignments.scope_type', 'installation')
            ->whereNull('user_role_assignments.scope_id')
            ->whereNull('user_role_assignments.revoked_at')
            ->where(fn ($query) => $query->whereNull('user_role_assignments.expires_at')->orWhere('user_role_assignments.expires_at', '>', now()))
            ->where(fn ($query) => $query->where('role_permissions.permission_key', 'birthdays.read')->orWhere('user_role_assignments.role_key', 'super_administrator'))
            ->distinct()
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email']);
    }
}
