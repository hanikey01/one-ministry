<?php

namespace App\Services;

use App\Models\EmailDeliverySetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailDeliverySettingsService
{
    /** @return array<string, array{name:string, host:?string, port:?int, encryption:?string, note:string}> */
    public static function providers(): array
    {
        return [
            'hosting' => ['name' => 'Hosting default mailer', 'host' => null, 'port' => null, 'encryption' => null, 'note' => 'Uses the mail service provided by your web host.'],
            'custom_smtp' => ['name' => 'Custom SMTP', 'host' => null, 'port' => 587, 'encryption' => 'tls', 'note' => 'Use the SMTP details supplied by your provider.'],
            'resend' => ['name' => 'Resend SMTP', 'host' => 'smtp.resend.com', 'port' => 465, 'encryption' => 'ssl', 'note' => 'Use your Resend SMTP username and API key as the password.'],
            'postmark' => ['name' => 'Postmark SMTP', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use your Postmark server token as the password.'],
            'sendgrid' => ['name' => 'SendGrid SMTP', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use username apikey and an API key as the password.'],
            'mailgun' => ['name' => 'Mailgun SMTP', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use the SMTP credentials issued for your Mailgun domain.'],
            'brevo' => ['name' => 'Brevo SMTP', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use the SMTP login and key from Brevo.'],
            'google' => ['name' => 'Google Workspace / Gmail SMTP', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use an app password; ordinary account passwords do not work.'],
            'amazon_ses' => ['name' => 'Amazon SES SMTP', 'host' => null, 'port' => 587, 'encryption' => 'tls', 'note' => 'Enter the regional SES SMTP endpoint and SMTP credentials.'],
            'zoho' => ['name' => 'Zoho Mail SMTP', 'host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use a Zoho app password where required.'],
        ];
    }

    public function setting(): EmailDeliverySetting
    {
        return EmailDeliverySetting::query()->firstOrCreate(['id' => EmailDeliverySetting::SINGLETON_ID], ['provider' => 'hosting', 'enabled' => false]);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data, User $actor, ?string $ipAddress): EmailDeliverySetting
    {
        return DB::transaction(function () use ($data, $actor, $ipAddress): EmailDeliverySetting {
            $setting = EmailDeliverySetting::query()->lockForUpdate()->firstOrCreate(['id' => EmailDeliverySetting::SINGLETON_ID], ['provider' => 'hosting', 'enabled' => false]);
            $provider = $data['provider'];
            $preset = self::providers()[$provider];
            $attributes = [
                'provider' => $provider,
                'enabled' => (bool) ($data['enabled'] ?? false),
                'from_address' => $data['from_address'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'host' => $provider === 'hosting' ? null : ($data['host'] ?: $preset['host']),
                'port' => $provider === 'hosting' ? null : ($data['port'] ?: $preset['port']),
                'encryption' => $provider === 'hosting' ? null : ($data['encryption'] ?: $preset['encryption']),
                'username' => $provider === 'hosting' ? null : ($data['username'] ?? null),
                'updated_by' => $actor->id,
            ];
            if ($provider === 'hosting') {
                $attributes['password_encrypted'] = null;
            } elseif (! empty($data['password'])) {
                $attributes['password_encrypted'] = Crypt::encryptString($data['password']);
            }
            $candidate = clone $setting;
            $candidate->fill($attributes);
            if ($candidate->enabled && ! $this->ready($candidate)) {
                throw ValidationException::withMessages(['provider' => 'Complete the sender and delivery details before activating email delivery.']);
            }
            $before = $setting->only(['provider', 'enabled', 'from_address', 'from_name', 'host', 'port', 'encryption', 'username']);
            $setting->fill($attributes);
            $changes = $setting->getDirty();
            if ($changes !== []) {
                $setting->save();
                DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => 'email_delivery_settings.updated', 'subject_type' => 'email_delivery_settings', 'subject_id' => $setting->id, 'changes_json' => json_encode(['before' => array_intersect_key($before, $changes), 'after' => array_intersect_key($changes, array_flip(array_keys($before)))], JSON_THROW_ON_ERROR), 'ip_address' => $ipAddress, 'created_at' => now()]);
            }
            return $setting;
        });
    }

    public function sendRaw(string $recipient, string $subject, string $body): void
    {
        $setting = $this->setting();
        $this->ensureActive($setting);
        $mailer = 'ministry_delivery';
        if ($setting->provider === 'hosting') {
            config(['mail.mailers.'.$mailer => ['transport' => 'sendmail', 'path' => config('mail.mailers.sendmail.path')]]);
        } else {
            config(['mail.mailers.'.$mailer => ['transport' => 'smtp', 'host' => $setting->host, 'port' => $setting->port, 'scheme' => $setting->encryption, 'username' => $setting->username, 'password' => Crypt::decryptString((string) $setting->password_encrypted), 'timeout' => 15, 'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST)]]);
        }
        Mail::purge($mailer);
        Mail::mailer($mailer)->raw($body, function ($message) use ($setting, $recipient, $subject): void {
            $message->to($recipient)->subject($subject);
            $message->from((string) $setting->from_address, (string) $setting->from_name);
        });
    }

    public function ready(EmailDeliverySetting $setting): bool
    {
        if (! filter_var($setting->from_address, FILTER_VALIDATE_EMAIL)) return false;
        if ($setting->provider === 'hosting') return true;
        return filled($setting->host) && filled($setting->port) && filled($setting->username) && filled($setting->password_encrypted);
    }

    public function active(): bool
    {
        $setting = $this->setting();

        return $setting->enabled && $this->ready($setting);
    }

    public function ensureActive(?EmailDeliverySetting $setting = null): void
    {
        $setting ??= $this->setting();
        if (! $setting->enabled || ! $this->ready($setting)) {
            abort(503, 'Account email delivery is not configured. Contact the ministry administrator.');
        }
    }
}
