<?php

namespace Tests\Unit;

use App\Services\Payments\StripePaymentProcessor;
use PHPUnit\Framework\TestCase;

class StripePaymentProcessorTest extends TestCase
{
    public function test_stripe_signature_is_verified_with_timestamped_payload(): void
    {
        $processor = new StripePaymentProcessor();
        $payload = '{"id":"evt_test"}';
        $timestamp = time();
        $secret = 'whsec_test';
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $this->assertTrue($processor->validSignature($payload, "t={$timestamp},v1={$signature}", $secret));
        $this->assertFalse($processor->validSignature($payload, "t=".($timestamp - 301).",v1={$signature}", $secret));
    }

    public function test_statuses_are_normalized_to_domain_states(): void
    {
        $processor = new StripePaymentProcessor();

        $this->assertSame('confirmed', $processor->normalizeStatus('paid'));
        $this->assertSame('failed', $processor->normalizeStatus('payment_failed'));
        $this->assertSame('pending', $processor->normalizeStatus('processing'));
    }
}
