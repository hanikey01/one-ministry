<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface PaymentProcessor
{
    public function key(): string;

    public function label(): string;

    public function supports(string $country, string $currency): bool;

    public function createCheckout(object $donation, array $context = []): array;

    public function verifyTransaction(object $donation, array $context = []): array;

    public function handleWebhook(Request $request): array;

    public function refund(object $donation, ?int $amountMinor = null): array;

    public function normalizeStatus(string $status): string;
}
