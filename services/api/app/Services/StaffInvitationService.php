<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffInvitationService
{
    public function __construct(private readonly EmailTemplateService $templates) {}

    public function create(string $name, string $email, string $roleKey, User $actor, ?string $ip): array
    {
        $email = Str::lower($email);

        $invitation = DB::transaction(function () use ($name, $email, $roleKey, $actor, $ip): array {
            if (! in_array($roleKey, RoleCatalog::ROUTINE_INVITATION_ROLES, true) || ! DB::table('roles')->where('key', $roleKey)->exists()) {
                throw ValidationException::withMessages(['role_key' => 'Choose one of the available staff roles.']);
            }
            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'This email already has an account.']);
            }
            if (DB::table('staff_invitations')->where('email', $email)->whereNull('accepted_at')
                ->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['email' => 'An active invitation already exists.']);
            }

            $token = bin2hex(random_bytes(32));
            $id = (string) Str::uuid();
            $expiresAt = now()->addDay();
            DB::table('staff_invitations')->insert([
                'id' => $id, 'name' => $name, 'email' => $email,
                'role_key' => $roleKey, 'token_hash' => hash('sha256', $token),
                'invited_by' => $actor->id, 'expires_at' => $expiresAt,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($actor, 'staff_invitation.created', $id, ['email' => $email, 'role' => $roleKey], $ip);

            return [
                'id' => $id, 'name' => $name, 'email' => $email,
                'role_key' => $roleKey, 'expires_at' => $expiresAt->toIso8601String(),
                'accept_url' => route('staff.invitation.accept.page', ['token' => $token]),
            ];
        });

        try {
            $ministry = \App\Models\MinistrySetting::findOrFail(\App\Models\MinistrySetting::SINGLETON_ID);
            $this->templates->send('staff_invitation', $email, ['invitee_name' => $name, 'inviter_name' => $actor->name, 'ministry_name' => $ministry->name, 'role_name' => RoleCatalog::INSTALLATION_ROLES[$roleKey] ?? Str::headline($roleKey), 'expires_hours' => 24, 'action_url' => $invitation['accept_url']], $name, 'Accept invitation');
        } catch (\Throwable $error) {
            DB::table('staff_invitations')->where('id', $invitation['id'])->update(['revoked_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'staff_invitation.delivery_failed', $invitation['id'], ['email' => $email], $ip);
            throw $error;
        }

        return $invitation;
    }

    public function accept(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password): void {
            $invitation = DB::table('staff_invitations')->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->first();
            if ($invitation === null || $invitation->accepted_at !== null || $invitation->revoked_at !== null || now()->gte($invitation->expires_at)) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or has expired.']);
            }
            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['token' => 'This invitation can no longer be accepted.']);
            }

            $user = User::create(['name' => $invitation->name, 'email' => $invitation->email, 'password' => $password]);
            DB::table('user_role_assignments')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $user->id,
                'role_key' => $invitation->role_key, 'scope_type' => 'installation',
                'granted_by' => $invitation->invited_by,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('staff_invitations')->where('id', $invitation->id)->update([
                'accepted_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($user, 'staff_invitation.accepted', $invitation->id, ['role' => $invitation->role_key], null);
        });
    }

    public function revoke(string $id, User $actor, ?string $ip): bool
    {
        return DB::transaction(function () use ($id, $actor, $ip): bool {
            $invitation = DB::table('staff_invitations')->where('id', $id)->lockForUpdate()->first();
            if ($invitation === null || $invitation->accepted_at !== null || $invitation->revoked_at !== null) {
                return false;
            }
            DB::table('staff_invitations')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'staff_invitation.revoked', $id, ['email' => $invitation->email], $ip);

            return true;
        });
    }

    private function audit(User $actor, string $action, string $subjectId, array $changes, ?string $ip): void
    {
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id,
            'action' => $action, 'subject_type' => 'staff_invitations', 'subject_id' => $subjectId,
            'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR),
            'ip_address' => $ip, 'created_at' => now(),
        ]);
    }
}
