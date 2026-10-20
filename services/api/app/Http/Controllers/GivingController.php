<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GivingController extends Controller
{
    public function funds(): JsonResponse
    {
        return response()->json(['data' => DB::table('giving_funds')->where('active', true)->get(['id', 'name', 'description'])]);
    }

    public function createFund(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000']]);
        $id = (string) Str::uuid();
        DB::table('giving_funds')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('giving_funds')->where('id', $id)->first()], 201);
    }

    public function intent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fund_id' => ['required', 'uuid', 'exists:giving_funds,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'receipt_email' => ['nullable', 'email', 'max:254'],
        ]);
        $id = (string) Str::uuid();
        DB::table('donations')->insert([...$data, 'id' => $id, 'provider' => 'configured_provider', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['donation_id' => $id, 'status' => 'pending', 'next_action' => 'provider_checkout']], 201);
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('services.giving.webhook_secret');
        abort_if($secret === '', 503, 'Giving webhook verification is not configured.');
        $signature = (string) $request->header('X-Giving-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Webhook signature is invalid.');

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'donation_id' => ['nullable', 'uuid', 'exists:donations,id'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'occurred_at' => ['required', 'date'],
        ]);
        if (DB::table('payment_events')->where('provider', $provider)->where('provider_event_id', $data['event_id'])->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        DB::transaction(function () use ($data, $provider): void {
            $donationId = $data['donation_id'] ?? null;
            if ($donationId !== null && $data['type'] === 'payment.succeeded') {
                DB::table('donations')->where('id', $donationId)->where('status', 'pending')->update([
                    'status' => 'confirmed', 'provider' => $provider, 'provider_reference' => $data['provider_reference'] ?? null,
                    'confirmed_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('payment_events')->insert([
                'id' => (string) Str::uuid(), 'donation_id' => $donationId, 'provider' => $provider, 'provider_event_id' => $data['event_id'],
                'event_type' => $data['type'], 'amount_minor' => $data['amount_minor'] ?? null, 'currency' => $data['currency'] ?? null,
                'occurred_at' => $data['occurred_at'], 'created_at' => now(),
            ]);
        });

        return response()->json(['status' => 'received'], 202);
    }

    public function donations(): JsonResponse
    {
        return response()->json(['data' => DB::table('donations')->latest()->paginate(50)]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:120', 'unique:reconciliation_batches,reference'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $id = (string) Str::uuid();
        DB::table('reconciliation_batches')->insert([...$data, 'id' => $id, 'created_by' => $request->user()->id, 'reconciled_at' => now()]);

        return response()->json(['data' => DB::table('reconciliation_batches')->where('id', $id)->first()], 201);
    }
}
