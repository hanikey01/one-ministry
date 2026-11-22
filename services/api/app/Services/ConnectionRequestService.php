<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Handles public and member connection requests without creating an account for a guest. */
class ConnectionRequestService
{
    /** @param array{first_name?: string, last_name?: string|null, email?: string, phone?: string|null, consent_to_contact?: bool} $contact */
    public function person(Request $request, array $contact = []): object
    {
        if ($request->user()) {
            $person = DB::table('people')->where('user_id', $request->user()->id)->first();
            if ($person) {
                return $person;
            }

            $names = preg_split('/\s+/', trim($request->user()->name), 2);
            $id = (string) Str::uuid();
            DB::table('people')->insert([
                'id' => $id, 'user_id' => $request->user()->id, 'first_name' => $names[0] ?: 'Member',
                'last_name' => $names[1] ?? null, 'email' => $request->user()->email, 'status' => 'member',
                'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('people')->where('id', $id)->first();
        }

        $email = mb_strtolower(trim((string) ($contact['email'] ?? '')));
        $person = $this->existingPersonForEmail($email);
        if ($person) {
            if (($contact['consent_to_contact'] ?? false) && ! $person->consent_to_contact && ! $person->user_id) {
                DB::table('people')->where('id', $person->id)->update(['consent_to_contact' => true, 'updated_at' => now()]);
                $person->consent_to_contact = true;
            }

            return $person;
        }

        $id = (string) Str::uuid();
        DB::table('people')->insert([
            'id' => $id,
            'first_name' => trim((string) $contact['first_name']),
            'last_name' => filled($contact['last_name'] ?? null) ? trim((string) $contact['last_name']) : null,
            'email' => $email,
            'phone' => filled($contact['phone'] ?? null) ? trim((string) $contact['phone']) : null,
            'status' => 'member',
            'consent_to_contact' => (bool) ($contact['consent_to_contact'] ?? false),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('people')->where('id', $id)->first();
    }

    /**
     * Resolve a typed email to the installation's person record. A matching
     * account is linked to its person record, but callers must not treat this
     * as authentication or populate authenticated ownership fields from it.
     */
    public function existingPersonForEmail(?string $email): ?object
    {
        $normalized = mb_strtolower(trim((string) $email));
        if ($normalized === '') {
            return null;
        }

        return DB::transaction(function () use ($normalized): ?object {
            $user = DB::table('users')->whereRaw('lower(email) = ?', [$normalized])->first(['id', 'name', 'email']);
            if ($user) {
                $person = DB::table('people')->where('user_id', $user->id)->first();
                if ($person) {
                    return $person;
                }

                $person = DB::table('people')->whereNull('user_id')->whereRaw('lower(email) = ?', [$normalized])->orderBy('created_at')->lockForUpdate()->first();
                if ($person) {
                    DB::table('people')->where('id', $person->id)->update(['user_id' => $user->id, 'updated_at' => now()]);

                    return DB::table('people')->where('id', $person->id)->first();
                }

                $names = preg_split('/\s+/', trim((string) $user->name), 2);
                $id = (string) Str::uuid();
                DB::table('people')->insert([
                    'id' => $id, 'user_id' => $user->id, 'first_name' => $names[0] ?: 'Member',
                    'last_name' => $names[1] ?? null, 'email' => mb_strtolower((string) $user->email),
                    'status' => 'member', 'consent_to_contact' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return DB::table('people')->where('id', $id)->first();
            }

            return DB::table('people')->whereRaw('lower(email) = ?', [$normalized])->orderBy('created_at')->first();
        });
    }

    public function submitPrayer(Request $request, object $person, array $data): void
    {
        DB::table('prayer_requests')->insert([
            'id' => (string) Str::uuid(), 'person_id' => $person->id, 'user_id' => $request->user()?->id,
            'prayer_text_encrypted' => Crypt::encryptString(trim($data['prayer_text'])),
            'visibility' => 'prayer_team', 'consent_to_contact' => (bool) ($data['consent_to_contact'] ?? false),
            'status' => 'received', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($request, 'prayer.request_submitted', 'prayer_requests', $person->id, ['source' => $request->user() ? 'member' : 'guest']);
    }

    public function submitNextStep(Request $request, object $person, string $type): bool
    {
        $duplicate = DB::table('follow_up_tasks')->where('person_id', $person->id)->where('kind', 'next_step')
            ->where('request_key', $type)->whereIn('status', ['open', 'in_progress'])->exists();
        if ($duplicate) {
            return false;
        }

        $label = DB::table('next_step_options')->where('key', $type)->value('label');
        DB::table('follow_up_tasks')->insert([
            'id' => (string) Str::uuid(), 'person_id' => $person->id, 'kind' => 'next_step', 'request_key' => $type,
            'status' => 'open', 'summary' => 'Next Step request: '.$label, 'due_at' => now()->addDays(3),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($request, 'next_step.request_submitted', 'follow_up_tasks', $person->id, ['type' => $type, 'source' => $request->user() ? 'member' : 'guest']);

        return true;
    }

    public function rejectBot(array $data): void
    {
        if (filled($data['website'] ?? null)) {
            throw ValidationException::withMessages(['form' => 'We could not accept this request. Please try again.']);
        }
    }

    private function audit(Request $request, string $action, string $subjectType, string $personId, array $changes): void
    {
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()?->id, 'action' => $action,
            'subject_type' => $subjectType, 'subject_id' => $personId, 'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(), 'created_at' => now(),
        ]);
    }
}
