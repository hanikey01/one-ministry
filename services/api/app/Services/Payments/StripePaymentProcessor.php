<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProcessor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePaymentProcessor implements PaymentProcessor
{
    private const API = 'https://api.stripe.com/v1';

    public function key(): string { return 'stripe'; }
    public function label(): string { return 'Stripe'; }

    public function supports(string $country, string $currency): bool
    {
        return preg_match('/^[A-Z]{2}$/', strtoupper($country)) === 1 && preg_match('/^[A-Z]{3}$/', strtoupper($currency)) === 1;
    }

    public function createCheckout(object $donation, array $context = []): array
    {
        $this->assertMoney($donation);
        $params = [
            'mode' => 'payment',
            'line_items[0][price_data][currency]' => strtolower($donation->currency),
            'line_items[0][price_data][product_data][name]' => $context['fund_name'] ?? 'Ministry donation',
            'line_items[0][price_data][unit_amount]' => $donation->amount_minor,
            'line_items[0][quantity]' => 1,
            'success_url' => $context['success_url'] ?? throw new RuntimeException('Stripe success URL is required.'),
            'cancel_url' => $context['cancel_url'] ?? throw new RuntimeException('Stripe cancel URL is required.'),
            'client_reference_id' => $donation->internal_reference ?? $donation->id,
            'payment_intent_data[metadata][donation_id]' => $donation->id,
            'payment_intent_data[metadata][internal_reference]' => $donation->internal_reference ?? $donation->id,
        ];
        if (filled($donation->receipt_email ?? null)) $params['customer_email'] = $donation->receipt_email;

        return $this->request($context)->asForm()->post('/checkout/sessions', $params)->throw()->json();
    }

    public function verifyTransaction(object $donation, array $context = []): array
    {
        $sessionId = $context['session_id'] ?? $donation->provider_reference;
        if (! is_string($sessionId) || $sessionId === '') throw new RuntimeException('Stripe session reference is required.');
        $session = $this->request($context)->get('/checkout/sessions/'.rawurlencode($sessionId), ['expand[]' => 'payment_intent'])->throw()->json();
        $this->assertMoney((object) ['amount_minor' => $session['amount_total'] ?? null, 'currency' => $session['currency'] ?? null], $donation);
        return ['provider_reference' => $session['id'], 'status' => $this->normalizeStatus((string) ($session['payment_status'] ?? $session['status'] ?? 'pending')), 'customer_id' => $session['customer'] ?? null, 'payment_intent_id' => is_array($session['payment_intent'] ?? null) ? ($session['payment_intent']['id'] ?? null) : ($session['payment_intent'] ?? null)];
    }

    public function handleWebhook(Request $request): array
    {
        $secret = (string) ($request->attributes->get('stripe_webhook_secret') ?? '');
        $signature = (string) $request->header('Stripe-Signature');
        if ($secret === '' || ! $this->validSignature($request->getContent(), $signature, $secret)) throw new RuntimeException('Stripe webhook signature is invalid.');
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $object = $payload['data']['object'] ?? [];
        return ['event_id' => $payload['id'] ?? throw new RuntimeException('Stripe event ID is missing.'), 'type' => $payload['type'] ?? 'unknown', 'donation_id' => $object['metadata']['donation_id'] ?? $object['client_reference_id'] ?? null, 'provider_reference' => $object['id'] ?? null, 'amount_minor' => $object['amount_total'] ?? $object['amount'] ?? null, 'currency' => strtoupper((string) ($object['currency'] ?? '')), 'status' => $this->normalizeStatus($object['payment_status'] ?? $object['status'] ?? 'pending'), 'payload' => $payload];
    }

    public function refund(object $donation, ?int $amountMinor = null): array
    {
        $paymentIntent = $donation->payment_intent_id ?? null;
        if (! $paymentIntent) throw new RuntimeException('Stripe payment intent reference is required for refund.');
        $params = ['payment_intent' => $paymentIntent];
        if ($amountMinor !== null) $params['amount'] = $amountMinor;
        return $this->request(['credentials' => $donation->credentials ?? []])->asForm()->post('/refunds', $params)->throw()->json();
    }

    public function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) { 'paid', 'succeeded', 'complete', 'completed' => 'confirmed', 'failed', 'payment_failed', 'unpaid', 'canceled', 'cancelled', 'expired' => 'failed', 'refunded' => 'refunded', default => 'pending' };
    }

    public function validSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $parts = collect(explode(',', $header))->mapWithKeys(function (string $part): array { [$key, $value] = array_pad(explode('=', trim($part), 2), 2, ''); return [$key => $value]; });
        $timestamp = (int) ($parts['t'] ?? 0);
        if ($timestamp < 1 || abs(time() - $timestamp) > $tolerance) return false;
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        return hash_equals($expected, (string) ($parts['v1'] ?? ''));
    }

    private function request(array $context): PendingRequest
    {
        $secret = $context['credentials']['secret_key'] ?? null;
        if (! is_string($secret) || $secret === '') throw new RuntimeException('Stripe secret key is not configured.');
        return Http::baseUrl(self::API)->withBasicAuth($secret, '')->acceptJson()->timeout(15)->retry(2, 200);
    }

    private function assertMoney(object $actual, ?object $expected = null): void
    {
        if (! is_int($actual->amount_minor) && ! ctype_digit((string) $actual->amount_minor)) throw new RuntimeException('Stripe returned an invalid amount.');
        if ($expected && ((int) $actual->amount_minor !== (int) $expected->amount_minor || strtolower((string) $actual->currency) !== strtolower((string) $expected->currency))) throw new RuntimeException('Stripe amount or currency does not match the donation.');
    }
}
