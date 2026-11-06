<?php

namespace App\Services;

use App\Models\EmailDeliverySetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailDeliverySettingsService
{
    /** @return array<string, array{name:string, host:?string, port:?int, encryption:?string, note:string}> */
    public static function providers(): array
    {
        return [
            'custom_smtp' => ['name' => 'Custom SMTP', 'host' => null, 'port' => 587, 'encryption' => 'tls', 'note' => 'Use the SMTP details supplied by your provider.'],
            'resend' => ['name' => 'Resend SMTP', 'host' => 'smtp.resend.com', 'port' => 465, 'encryption' => 'ssl', 'note' => 'Use your Resend SMTP username and API key as the password.'],
            'postmark' => ['name' => 'Postmark SMTP', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use your Postmark server token as the password.'],
            'mailchimp_transactional' => ['name' => 'Mailchimp Transactional', 'host' => 'smtp.mandrillapp.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use your Mailchimp Transactional API key as the SMTP password.'],
        ];
    }

    public function setting(): EmailDeliverySetting
    {
        $setting = EmailDeliverySetting::query()->firstOrCreate(['id' => EmailDeliverySetting::SINGLETON_ID], ['provider' => 'custom_smtp', 'enabled' => false]);
        if (! array_key_exists((string) $setting->provider, self::providers())) {
            $setting->forceFill(['provider' => 'custom_smtp'])->save();
        }
        return $setting;
    }

    /** @param array<string, mixed> $data */
    public function update(array $data, User $actor, ?string $ipAddress): EmailDeliverySetting
    {
        return DB::transaction(function () use ($data, $actor, $ipAddress): EmailDeliverySetting {
            $setting = EmailDeliverySetting::query()->lockForUpdate()->firstOrCreate(['id' => EmailDeliverySetting::SINGLETON_ID], ['provider' => 'custom_smtp', 'enabled' => false]);
            $provider = $data['provider'];
            $preset = self::providers()[$provider];
            $attributes = [
                'provider' => $provider,
                'enabled' => (bool) ($data['enabled'] ?? false),
                'from_address' => $data['from_address'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'host' => $this->normalizeHost($data['host'] ?: $preset['host']),
                'port' => $data['port'] ?: $preset['port'],
                'encryption' => array_key_exists('encryption', $data) ? $data['encryption'] : $preset['encryption'],
                'username' => $data['username'] ?? null,
                'updated_by' => $actor->id,
            ];
            if (! empty($data['password'])) {
                $attributes['password_encrypted'] = Crypt::encryptString($data['password']);
            } elseif ($setting->provider !== $provider) {
                $attributes['password_encrypted'] = null;
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
        $this->send($recipient, $subject, null, $body);
    }

    public function sendHtml(string $recipient, string $subject, string $html, string $text, ?string $recipientName = null): void
    {
        $this->send($recipient, $subject, $html, $text, $recipientName);
    }

    public function test(string $recipient, User $actor, ?string $ipAddress): void
    {
        $setting = $this->setting();
        try {
            $this->sendRaw($recipient, 'Email delivery test', "Your ministry email delivery connection is working.\n\nSent at ".now()->toIso8601String().'.');
            $setting->forceFill(['last_tested_at' => now(), 'last_test_succeeded_at' => now(), 'last_test_status' => 'succeeded', 'last_test_error' => null])->save();
            Log::info('Ministry test email accepted by mail transport.', ['provider' => $setting->provider, 'recipient' => $recipient]);
            $this->auditTest($setting->id, $actor, true, $ipAddress);
        } catch (Throwable $error) {
            $setting->forceFill(['last_tested_at' => now(), 'last_test_status' => 'failed', 'last_test_error' => Str::limit($this->safeError($error), 500, '')])->save();
            Log::warning('Ministry test email was rejected by the configured mail transport.', ['provider' => $setting->provider, 'recipient' => $recipient, 'error' => $this->safeError($error)]);
            $this->auditTest($setting->id, $actor, false, $ipAddress);
            throw ValidationException::withMessages(['test_email' => 'The test email could not be sent. '.$this->safeError($error)]);
        }
    }

    public function ready(EmailDeliverySetting $setting): bool
    {
        if (! filter_var($setting->from_address, FILTER_VALIDATE_EMAIL) || ! filled($setting->from_name)) return false;
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

    private function send(string $recipient, string $subject, ?string $html, string $text, ?string $recipientName = null): void
    {
        $setting = $this->setting();
        $this->ensureActive($setting);
        $mailer = 'ministry_delivery';
        config(['mail.mailers.'.$mailer => ['transport' => 'smtp', 'host' => $this->normalizeHost($setting->host), 'port' => $setting->port, 'scheme' => $setting->encryption === 'ssl' ? 'smtps' : 'smtp', 'username' => $setting->username, 'password' => Crypt::decryptString((string) $setting->password_encrypted), 'timeout' => 15, 'auto_tls' => $setting->encryption === 'tls', 'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST)]]);
        Mail::purge($mailer);
        $callback = function ($message) use ($setting, $recipient, $recipientName, $subject): void {
            $message->to($recipient, $recipientName)->subject($subject);
            $message->from((string) $setting->from_address, (string) $setting->from_name);
        };
        if ($html === null) Mail::mailer($mailer)->raw($text, $callback);
        else Mail::mailer($mailer)->html($html, $callback);
    }

    private function normalizeHost(?string $host): ?string
    {
        $host = trim((string) $host);
        if ($host === '') return null;
        if (str_contains($host, '://')) $host = (string) (parse_url($host, PHP_URL_HOST) ?: $host);
        return trim($host, " \t\n\r\0\x0B/");
    }

    private function auditTest(string $settingId, User $actor, bool $succeeded, ?string $ipAddress): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => 'email_delivery.tested', 'subject_type' => 'email_delivery_settings', 'subject_id' => $settingId, 'changes_json' => json_encode(['succeeded' => $succeeded], JSON_THROW_ON_ERROR), 'ip_address' => $ipAddress, 'created_at' => now()]);
    }

    private function safeError(Throwable $error): string
    {
        $message = preg_replace('/(password|token|key|secret)=?[^\s;]*/i', '$1=[redacted]', $error->getMessage());
        return filled($message) ? (string) $message : 'Check the provider credentials, sender identity, port, and encryption settings.';
    }
}
