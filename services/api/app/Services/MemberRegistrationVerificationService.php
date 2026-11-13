<?php

namespace AppServices;

use AppModelsUser;
use IlluminateSupportFacadesDB;
use IlluminateSupportFacadesHash;
use IlluminateSupportStr;
use IlluminateValidationValidationException;

final class MemberRegistrationVerificationService
{
    public function __construct(private readonly EmailTemplateService $templates) {}

    public function request(string $name, string $email): string
    {
        $existing = DB::table('member_registration_verifications')->where('email', $email)->first();
        if ($existing && now()->diffInSeconds($existing->sent_at) < 60) {
            throw ValidationException::withMessages(['email' => 'Please wait a minute before requesting another code.']);
        }

        $code = (string) random_int(100000, 999999);
        $id = $existing?->id ?? (string) Str::uuid();
        DB::table('member_registration_verifications')->updateOrInsert(['email' => $email], [
            'id' => $id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10),
            'attempts' => 0, 'verified_at' => null, 'sent_at' => now(), 'updated_at' => now(),
            'created_at' => $existing?->created_at ?? now(),
        ]);

        try {
            $this->templates->send('member_signup_verification', $email, ['member_name' => $name, 'verification_code' => $code, 'expires_minutes' => 10], $name);
        } catch (Throwable $error) {
            DB::table('member_registration_verifications')->where('id', $id)->delete();
            throw $error;
        }

        return $id;
    }

    public function verify(string $id, string $email, string $code): void
    {
        DB::transaction(function () use ($id, $email, $code): void {
            $record = DB::table('member_registration_verifications')->where('id', $id)->where('email', $email)->lockForUpdate()->first();
            if ($record === null || $record->verified_at !== null || now()->gte($record->expires_at) || $record->attempts >= 5) {
                throw ValidationException::withMessages(['code' => 'This code is invalid or has expired. Request a new one to continue.']);
            }
            if (! Hash::check($code, $record->code_hash)) {
                DB::table('member_registration_verifications')->where('id', $id)->increment('attempts', 1, ['updated_at' => now()]);
                throw ValidationException::withMessages(['code' => 'That code is not correct. Please try again.']);
            }
            DB::table('member_registration_verifications')->where('id', $id)->update(['verified_at' => now(), 'updated_at' => now()]);
        });
    }

    public function complete(string $id, string $name, string $email, string $password, ?string $ip): User
    {
        return DB::transaction(function () use ($id, $name, $email, $password, $ip): User {
            $record = DB::table('member_registration_verifications')->where('id', $id)->where('email', $email)->lockForUpdate()->first();
            if ($record === null || $record->verified_at === null || now()->gte($record->expires_at) || User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'This verified registration is no longer available. Start again to continue.']);
            }
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password, 'email_verified_at' => now()]);
            $names = preg_split('/\s+/', trim($name), 2);
            $person = DB::table('people')->whereNull('user_id')->where('email', $email)->first();
            if ($person) DB::table('people')->where('id', $person->id)->update(['user_id' => $user->id, 'status' => 'member', 'updated_at' => now()]);
            else DB::table('people')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'first_name' => $names[0] ?: 'Member', 'last_name' => $names[1] ?? null, 'email' => $email, 'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('member_registration_verifications')->where('id', $id)->delete();
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $user->id, 'action' => 'auth.member_registered', 'subject_type' => 'users', 'subject_id' => $user->id, 'changes_json' => json_encode(['channel' => 'public_signup', 'email_verified' => true]), 'ip_address' => $ip, 'created_at' => now()]);
            return $user;
        });
    }
}
