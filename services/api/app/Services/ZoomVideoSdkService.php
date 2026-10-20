<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

class ZoomVideoSdkService
{
    public function __construct(private readonly ZoomVideoSdkSettingsService $settings)
    {
    }

    /** @return array{token:string,expires_at:string,session_name:string,session_passcode:string} */
    public function issueJoinGrant(string $sessionName, string $sessionPasscodeEncrypted, User $user, string $displayName, bool $host): array
    {
        $setting = $this->settings->setting();
        if (! $this->settings->ready()) {
            throw new RuntimeException('Zoom Video SDK is not configured for this ministry.');
        }

        $now = now()->subSeconds(30)->timestamp;
        $expiresAt = now()->addMinutes(30);
        $header = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'app_key' => $setting->sdk_key,
            'role_type' => $host ? 1 : 0,
            'tpc' => $sessionName,
            'version' => 1,
            'iat' => $now,
            'exp' => $expiresAt->timestamp,
            'user_identity' => substr((string) $user->id, 0, 36),
            'session_key' => Str::uuid()->toString(),
        ], JSON_THROW_ON_ERROR));
        $secret = Crypt::decryptString($setting->sdk_secret_encrypted);
        $signature = $this->base64Url(hash_hmac('sha256', $header.'.'.$payload, $secret, true));

        return [
            'token' => $header.'.'.$payload.'.'.$signature,
            'expires_at' => $expiresAt->toIso8601String(),
            'session_name' => $sessionName,
            'session_passcode' => Crypt::decryptString($sessionPasscodeEncrypted),
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
