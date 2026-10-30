<?php

namespace App\Services;

use App\Contracts\PaymentProcessor;
use InvalidArgumentException;

class PaymentProcessorRegistry
{
    /** @param iterable<PaymentProcessor> $processors */
    public function __construct(private readonly iterable $processors = []) {}

    /** @return array<string, PaymentProcessor> */
    public function all(): array
    {
        $result = [];
        foreach ($this->processors as $processor) $result[$processor->key()] = $processor;
        return $result;
    }

    public function get(string $key): PaymentProcessor
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown payment processor: {$key}");
    }
}
