<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

final class AiProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $diagnosticCode,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerStatus = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        // Never retain HTTP exceptions: they contain credentials and request bodies.
        parent::__construct($diagnosticCode, $httpStatus ?? 0);
    }

    public static function fromHttp(RequestException $exception): self
    {
        $status = $exception->response->status();
        $providerStatus = $exception->response->json('error.status');
        $safeStatus = is_string($providerStatus) && in_array($providerStatus, [
            'INVALID_ARGUMENT', 'UNAUTHENTICATED', 'PERMISSION_DENIED', 'NOT_FOUND',
            'RESOURCE_EXHAUSTED', 'UNAVAILABLE', 'INTERNAL', 'DEADLINE_EXCEEDED',
        ], true) ? $providerStatus : null;
        $message = strtolower((string) $exception->response->json('error.message', ''));
        $reason = $exception->response->json('error.details.0.reason');
        if ($reason === 'API_KEY_INVALID' || ($status === 400 && str_contains($message, 'api key not valid'))) {
            return new self('provider_invalid_key', $status, $safeStatus);
        }
        if (in_array($status, [400, 422], true) && str_contains($message, 'schema')) {
            return new self('provider_schema_rejected', $status, $safeStatus);
        }

        $retryAfter = null;
        if ($status === 429) {
            $header = $exception->response->header('Retry-After');
            if (is_string($header) && ctype_digit(trim($header))) {
                $retryAfter = max(15, min(300, (int) trim($header)));
            }
        }

        return new self(match ($status) {
            401 => 'provider_invalid_key',
            403 => 'provider_access_denied',
            404 => 'provider_model_unavailable',
            408, 504 => 'provider_timeout',
            429 => 'provider_rate_limited',
            400, 422 => 'provider_request_rejected',
            default => $status >= 500 ? 'provider_unavailable' : 'provider_http_error',
        }, $status, $safeStatus, $retryAfter);
    }

    public static function fromConnection(ConnectionException $exception): self
    {
        $timeout = str_contains($exception->getMessage(), 'cURL error 28') || str_contains(strtolower($exception->getMessage()), 'timed out');

        return new self($timeout ? 'provider_timeout' : 'provider_connection_failed');
    }
}
