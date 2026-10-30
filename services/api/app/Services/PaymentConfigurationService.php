<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class PaymentConfigurationService
{
    public function available(string $country, string $currency): array
    {
        return DB::table('payment_processor_configs')->where('enabled', true)->orderBy('priority')->get()
            ->filter(fn ($config) => $config->country === $country && $config->currency === strtoupper($currency) && filled($config->credentials_encrypted))
            ->map(fn ($config) => ['key' => $config->provider, 'display_name' => $config->display_name, 'mode' => $config->mode, 'priority' => $config->priority])
            ->values()->all();
    }

    public function credentials(object $config): array
    {
        return json_decode(Crypt::decryptString($config->credentials_encrypted), true, flags: JSON_THROW_ON_ERROR);
    }
}
