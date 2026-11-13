<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EmailCampaignService
{
    public function __construct(private readonly EmailDeliverySettingsService $delivery) {}

    public function eligibleRecipientCount(): int
    {
        return DB::table('people')->join('notification_preferences', 'notification_preferences.person_id', '=', 'people.id')
            ->where('notification_preferences.email_marketing', true)->whereNotNull('people.email')->where('people.email', '<>', '')->count();
    }

    /** @param array{name:string,subject:string,body:string} $data */
    public function create(array $data, User $actor, ?string $ip): string
    {
        $id = (string) Str::uuid();
        DB::table('email_campaigns')->insert([...$data, 'id' => $id, 'created_by' => $actor->id, 'eligible_recipient_count' => $this->eligibleRecipientCount(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('email_campaign.created', $id, $actor, $ip);
        return $id;
    }

    public function schedule(string $campaignId, Carbon $scheduledAt, User $actor, ?string $ip): void
    {
        $this->delivery->ensureActive();
        DB::transaction(function () use ($campaignId, $scheduledAt, $actor, $ip): void {
            $campaign = DB::table('email_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            abort_unless($campaign, 404);
            if (! in_array($campaign->status, ['draft', 'scheduled'], true)) throw ValidationException::withMessages(['campaign' => 'Only a draft or scheduled campaign can be scheduled.']);
            DB::table('email_campaign_deliveries')->where('campaign_id', $campaignId)->delete();
            $recipients = DB::table('people')->join('notification_preferences', 'notification_preferences.person_id', '=', 'people.id')
                ->where('notification_preferences.email_marketing', true)->whereNotNull('people.email')->where('people.email', '<>', '')
                ->select('people.id', 'people.email', DB::raw("COALESCE(NULLIF(people.preferred_name, ''), people.first_name) as recipient_name"))->get();
            foreach ($recipients as $recipient) DB::table('email_campaign_deliveries')->insert(['id' => (string) Str::uuid(), 'campaign_id' => $campaignId, 'person_id' => $recipient->id, 'recipient_email' => $recipient->email, 'recipient_name' => $recipient->recipient_name, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('email_campaigns')->where('id', $campaignId)->update(['status' => 'scheduled', 'scheduled_at' => $scheduledAt, 'eligible_recipient_count' => $recipients->count(), 'sent_count' => 0, 'failed_count' => 0, 'skipped_count' => 0, 'started_at' => null, 'completed_at' => null, 'updated_at' => now()]);
            $this->audit('email_campaign.scheduled', $campaignId, $actor, $ip, ['scheduled_at' => $scheduledAt->toIso8601String(), 'recipient_count' => $recipients->count()]);
        });
    }

    public function cancel(string $campaignId, User $actor, ?string $ip): void
    {
        $changed = DB::table('email_campaigns')->where('id', $campaignId)->whereIn('status', ['draft', 'scheduled'])->update(['status' => 'cancelled', 'updated_at' => now()]);
        abort_unless($changed, 422, 'This campaign can no longer be cancelled.');
        $this->audit('email_campaign.cancelled', $campaignId, $actor, $ip);
    }

    public function processDue(int $limit = 50): int
    {
        $limit = max(1, min($limit, 100));
        DB::table('email_campaigns')->where('status', 'scheduled')->where('scheduled_at', '<=', now())->update(['status' => 'sending', 'started_at' => DB::raw('COALESCE(started_at, CURRENT_TIMESTAMP)'), 'updated_at' => now()]);
        $deliveries = DB::table('email_campaign_deliveries')->join('email_campaigns', 'email_campaigns.id', '=', 'email_campaign_deliveries.campaign_id')
            ->where('email_campaigns.status', 'sending')->where('email_campaign_deliveries.status', 'queued')
            ->orderBy('email_campaigns.scheduled_at')->orderBy('email_campaign_deliveries.created_at')->limit($limit)
            ->select('email_campaign_deliveries.*', 'email_campaigns.subject', 'email_campaigns.body')->get();
        foreach ($deliveries as $item) $this->sendDelivery($item);
        $this->completeFinishedCampaigns();
        return $deliveries->count();
    }

    private function sendDelivery(object $item): void
    {
        $optedIn = DB::table('notification_preferences')->where('person_id', $item->person_id)->where('email_marketing', true)->exists();
        if (! $optedIn) { DB::table('email_campaign_deliveries')->where('id', $item->id)->where('status', 'queued')->update(['status' => 'skipped', 'updated_at' => now()]); return; }
        try {
            $html = view('emails.ministry-campaign', ['recipientName' => $item->recipient_name ?: 'there', 'subjectLine' => $item->subject, 'body' => $item->body])->render();
            $this->delivery->sendHtml($item->recipient_email, $item->subject, $html, $item->body, $item->recipient_name);
            DB::table('email_campaign_deliveries')->where('id', $item->id)->where('status', 'queued')->update(['status' => 'sent', 'attempts' => $item->attempts + 1, 'sent_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $error) {
            $attempts = $item->attempts + 1;
            DB::table('email_campaign_deliveries')->where('id', $item->id)->where('status', 'queued')->update(['status' => $attempts >= 3 ? 'failed' : 'queued', 'attempts' => $attempts, 'failed_at' => $attempts >= 3 ? now() : null, 'failure_code' => 'transport_rejected', 'updated_at' => now()]);
            Log::warning('Campaign email delivery was rejected.', ['campaign_id' => $item->campaign_id, 'delivery_id' => $item->id, 'attempt' => $attempts]);
        }
    }

    private function completeFinishedCampaigns(): void
    {
        DB::table('email_campaigns')->where('status', 'sending')->orderBy('updated_at')->get()->each(function (object $campaign): void {
            $counts = DB::table('email_campaign_deliveries')->where('campaign_id', $campaign->id)->selectRaw("SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) queued, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed, SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) skipped")->first();
            if ((int) $counts->queued > 0) return;
            DB::table('email_campaigns')->where('id', $campaign->id)->update(['status' => 'completed', 'sent_count' => (int) $counts->sent, 'failed_count' => (int) $counts->failed, 'skipped_count' => (int) $counts->skipped, 'completed_at' => now(), 'updated_at' => now()]);
        });
    }

    private function audit(string $action, string $campaignId, User $actor, ?string $ip, array $changes = []): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => $action, 'subject_type' => 'email_campaign', 'subject_id' => $campaignId, 'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now()]);
    }
}
