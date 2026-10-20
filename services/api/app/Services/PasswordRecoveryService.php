<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryService
{
    public function request(string $email): void
    {
        $this->ensureMailReady();
        $email = Str::lower($email);
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return;
        }

        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();
        if ($existing && Carbon::parse($existing->created_at)->gt(now()->subMinute())) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
            'token' => Hash::make($token), 'created_at' => now(),
        ]);

        $url = rtrim(config('app.url'), '/').'/admin/reset-password#token='.$token.'&email='.rawurlencode($email);
        Mail::raw("A password reset was requested for your ministry staff account. This link expires in 30 minutes.\n\n{$url}\n\nIf you did not request this, you can ignore this message.", function ($message) use ($email): void {
            $message->to($email)->subject('Reset your ministry staff password');
        });
    }

    public function reset(string $email, string $token, string $password, ?string $ip): void
    {
        $email = Str::lower($email);
        DB::transaction(function () use ($email, $token, $password, $ip): void {
            $record = DB::table('password_reset_tokens')->where('email', $email)->lockForUpdate()->first();
            $user = User::query()->where('email', $email)->first();
            if ($record === null || $user === null || Carbon::parse($record->created_at)->lt(now()->subMinutes(30)) || ! Hash::check($token, $record->token)) {
                throw ValidationException::withMessages(['token' => 'This reset link is invalid or has expired.']);
            }

            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $user->id,
                'action' => 'auth.password_reset', 'subject_type' => 'users', 'subject_id' => $user->id,
                'changes_json' => '{}', 'ip_address' => $ip, 'created_at' => now(),
            ]);
        });
    }

    private function ensureMailReady(): void
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");
        if ($transport === 'array' && app()->environment('testing')) {
            return;
        }
        if (! in_array($transport, ['smtp', 'ses', 'postmark', 'resend', 'sendmail'], true)) {
            abort(503, 'Account email delivery is not configured. Contact the ministry administrator.');
        }
    }
}
