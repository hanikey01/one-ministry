<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function request(User $user, EmailDeliverySettingsService $delivery): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }
        $delivery->ensureActive();
        $token = bin2hex(random_bytes(32));
        DB::table('email_verification_tokens')->where('user_id', $user->id)->whereNull('used_at')->delete();
        DB::table('email_verification_tokens')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now()]);
        $url = route('email.verify.show', ['token' => $token]);
        $delivery->sendRaw($user->email, 'Verify your ministry staff email', "Verify your ministry staff email address. This link expires in 30 minutes.\n\n{$url}");
    }

    public function verify(string $token, ?string $ip): void
    {
        DB::transaction(function () use ($token, $ip): void {
            $record = DB::table('email_verification_tokens')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if ($record === null || $record->used_at !== null || now()->gte($record->expires_at)) {
                throw ValidationException::withMessages(['token' => 'This verification link is invalid or has expired.']);
            }
            $user = User::query()->lockForUpdate()->find($record->user_id);
            if ($user === null) {
                throw ValidationException::withMessages(['token' => 'This verification link is invalid or has expired.']);
            }
            $user->forceFill(['email_verified_at' => now()])->save();
            DB::table('email_verification_tokens')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $user->id, 'action' => 'auth.email_verified', 'subject_type' => 'users', 'subject_id' => $user->id, 'changes_json' => '{}', 'ip_address' => $ip, 'created_at' => now()]);
        });
    }
}
