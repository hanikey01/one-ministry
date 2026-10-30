<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationConfirmationService
{
    public function apply(string $provider, array $event): string
    {
        return DB::transaction(function () use ($provider, $event): string {
            if (DB::table('payment_events')->where('provider', $provider)->where('provider_event_id', $event['event_id'])->exists()) return 'already_processed';
            $donation = filled($event['donation_id'] ?? null) ? DB::table('donations')->where('id', $event['donation_id'])->orWhere('internal_reference',$event['donation_id'])->lockForUpdate()->first() : null;
            if (! $donation && filled($event['provider_reference'] ?? null)) $donation = DB::table('donations')->where('internal_reference', $event['provider_reference'])->orWhere('provider_reference', $event['provider_reference'])->lockForUpdate()->first();
            $result = 'recorded';
            if ($donation) {
                $matches = (int) $donation->amount_minor === (int) ($event['amount_minor'] ?? -1)
                    && strtoupper($donation->currency) === strtoupper((string) ($event['currency'] ?? ''));
                if (($event['status'] ?? null) === 'confirmed' && $matches && $donation->status === 'pending') {
                    DB::table('donations')->where('id', $donation->id)->update([
                        'status' => 'confirmed', 'provider' => $provider,
                        'provider_reference' => $event['provider_reference'] ?? $donation->provider_reference,
                        'provider_customer_id' => $event['customer_id'] ?? null,
                        'payment_intent_id' => $event['payment_intent_id'] ?? null,
                        'confirmed_at' => now(), 'receipt_status' => 'pending', 'updated_at' => now(),
                    ]);
                    $result = 'confirmed';
                } elseif (($event['status'] ?? null) === 'confirmed' && ! $matches) {
                    $result = 'amount_or_currency_mismatch';
                } elseif (in_array($event['status'] ?? null, ['failed', 'expired'], true) && $donation->status === 'pending') {
                    DB::table('donations')->where('id', $donation->id)->update(['status' => 'failed', 'failure_reason' => $event['type'] ?? 'provider_failed', 'updated_at' => now()]);
                    $result = 'failed';
                } elseif (in_array($event['status'] ?? null, ['refunded', 'reversed'], true) && $donation->status === 'confirmed') {
                    DB::table('donations')->where('id', $donation->id)->update(['status' => $event['status'], 'refund_status' => $event['status'], 'updated_at' => now()]);
                    $result = $event['status'];
                }
            }
            DB::table('payment_events')->insert([
                'id' => (string) Str::uuid(), 'donation_id' => $donation?->id, 'provider' => $provider,
                'provider_event_id' => $event['event_id'], 'event_type' => $event['type'] ?? 'unknown',
                'amount_minor' => $event['amount_minor'] ?? null, 'currency' => $event['currency'] ?? null,
                'payload' => json_encode($event['payload'] ?? [], JSON_THROW_ON_ERROR), 'processing_result' => $result,
                'occurred_at' => $event['occurred_at'] ?? now(), 'received_at' => now(), 'created_at' => now(),
            ]);
            return $result;
        });
    }
}
