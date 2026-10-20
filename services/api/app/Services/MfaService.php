<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MfaService
{
    public function begin(User $user): array
    {
        $secret = $this->base32(random_bytes(20));
        DB::table('mfa_credentials')->updateOrInsert(['user_id' => $user->id], [
            'secret' => Crypt::encryptString($secret), 'recovery_code_hashes' => json_encode([]),
            'enabled_at' => null, 'updated_at' => now(), 'created_at' => now(),
        ]);
        $issuer = rawurlencode(config('app.name'));
        $label = rawurlencode(config('app.name').':'.$user->email);

        return ['secret' => $secret, 'otpauth_url' => "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30"];
    }

    public function enable(User $user, string $code): array
    {
        $credential = DB::table('mfa_credentials')->where('user_id', $user->id)->first();
        if ($credential === null || $credential->enabled_at !== null || ! $this->valid(Crypt::decryptString($credential->secret), $code)) {
            throw ValidationException::withMessages(['code' => 'Enter the current code from your authenticator app.']);
        }
        $codes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
        DB::table('mfa_credentials')->where('user_id', $user->id)->update([
            'recovery_code_hashes' => json_encode(array_map(fn ($value) => Hash::make($value), $codes)),
            'enabled_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($user, 'mfa.enabled');

        return $codes;
    }

    public function verify(User $user, string $code): bool
    {
        $credential = DB::table('mfa_credentials')->where('user_id', $user->id)->first();
        if ($credential === null || $credential->enabled_at === null) {
            return false;
        }
        $secret = Crypt::decryptString($credential->secret);
        if ($this->valid($secret, $code)) {
            return true;
        }
        $hashes = json_decode($credential->recovery_code_hashes, true) ?: [];
        foreach ($hashes as $index => $hash) {
            if (Hash::check(strtoupper($code), $hash)) {
                unset($hashes[$index]);
                DB::table('mfa_credentials')->where('user_id', $user->id)->update(['recovery_code_hashes' => json_encode(array_values($hashes)), 'updated_at' => now()]);
                $this->audit($user, 'mfa.recovery_code_used');

                return true;
            }
        }

        return false;
    }

    public function enabled(User $user): bool
    {
        return DB::table('mfa_credentials')->where('user_id', $user->id)->whereNotNull('enabled_at')->exists();
    }

    private function valid(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals($this->code($secret, intdiv(time(), 30) + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function code(string $secret, int $counter): string
    {
        $binary = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binary, $this->decode($secret), true);
        $offset = ord($hash[19]) & 15;
        $number = ((ord($hash[$offset]) & 127) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper($value)) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '';
            } $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }

    private function audit(User $user, string $action): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $user->id, 'action' => $action, 'subject_type' => 'users', 'subject_id' => $user->id, 'changes_json' => '{}', 'created_at' => now()]);
    }
}
