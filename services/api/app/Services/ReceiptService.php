<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceiptService
{
    public function issuePending(): int
    {
        $count = 0;
        DB::table('donations')->where('status', 'confirmed')->where('receipt_status', 'pending')->whereNotNull('receipt_email')->orderBy('confirmed_at')->chunkById(100, function ($donations) use (&$count): void {
            foreach ($donations as $donation) DB::transaction(function () use ($donation, &$count): void {
                if (DB::table('donation_receipts')->where('donation_id', $donation->id)->exists()) return;
                DB::table('donation_receipts')->insert(['id' => (string) Str::uuid(), 'donation_id' => $donation->id, 'receipt_number' => 'RCT-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)), 'email' => $donation->receipt_email, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('donations')->where('id', $donation->id)->update(['receipt_status' => 'issued', 'updated_at' => now()]);
                $count++;
            });
        }, 'id');
        return $count;
    }
}
