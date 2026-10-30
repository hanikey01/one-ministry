<?php

namespace App\Services;

use InvalidArgumentException;

class CurrencyAmount
{
    private const ZERO = ['BIF','CLP','DJF','GNF','ISK','JPY','KMF','KRW','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];
    private const THREE = ['BHD','IQD','JOD','KWD','LYD','OMR','TND'];

    public function exponent(string $currency): int
    {
        $currency = strtoupper($currency);
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) throw new InvalidArgumentException('Currency must be an ISO 4217 code.');
        return in_array($currency, self::ZERO, true) ? 0 : (in_array($currency, self::THREE, true) ? 3 : 2);
    }

    public function major(int $minor, string $currency): string
    {
        $exponent = $this->exponent($currency);
        return number_format($minor / (10 ** $exponent), $exponent, '.', '');
    }

    public function minor(string|int|float $major, string $currency): int
    {
        return (int) round((float) $major * (10 ** $this->exponent($currency)));
    }
}
