<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

final class PublicSubmissionGuard
{
    public function token(string $routeName): string
    {
        return Crypt::encryptString($routeName.'|'.now()->timestamp);
    }

    public function startedAt(string $token, string $routeName): ?int
    {
        try {
            [$route, $timestamp] = explode('|', Crypt::decryptString($token), 2);
        } catch (\Throwable) {
            return null;
        }

        if (! hash_equals($routeName, $route) || ! ctype_digit($timestamp)) {
            return null;
        }

        return (int) $timestamp;
    }
}
