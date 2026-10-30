<?php

namespace App\Services;

use App\Models\MinistrySetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EmailTemplateService
{
    public function __construct(private readonly EmailDeliverySettingsService $delivery) {}

    /** @return array<string, array{name:string,description:string,subject:string,body:string,variables:array<string,string>}> */
    public static function catalog(): array
    {
        return [
            'staff_email_verification' => ['name' => 'Staff email verification', 'description' => 'Sent when a staff member verifies their email address.', 'subject' => 'Verify your ministry staff email', 'body' => "Hello {{ user_name }},\n\nVerify your staff email address using the button below. The link expires in {{ expires_minutes }} minutes.", 'variables' => ['user_name' => 'Staff member name', 'expires_minutes' => 'Link lifetime', 'action_url' => 'Protected verification link']],
            'password_reset' => ['name' => 'Password reset', 'description' => 'Sent after a valid password recovery request.', 'subject' => 'Reset your ministry staff password', 'body' => "Hello {{ user_name }},\n\nA password reset was requested for your account. Use the button below within {{ expires_minutes }} minutes. If you did not request this, you can ignore this message.", 'variables' => ['user_name' => 'Account name', 'expires_minutes' => 'Link lifetime', 'action_url' => 'Protected reset link']],
            'staff_invitation' => ['name' => 'Staff invitation', 'description' => 'Sent when an administrator invites a staff member.', 'subject' => 'You are invited to serve with {{ ministry_name }}', 'body' => "Hello {{ invitee_name }},\n\n{{ inviter_name }} invited you to join {{ ministry_name }} as {{ role_name }}. Use the button below within {{ expires_hours }} hours.", 'variables' => ['invitee_name' => 'Invitee name', 'inviter_name' => 'Inviting staff member', 'ministry_name' => 'Ministry name', 'role_name' => 'Assigned role', 'expires_hours' => 'Invitation lifetime', 'action_url' => 'Protected invitation link']],
            'member_welcome' => ['name' => 'Member welcome', 'description' => 'Welcome copy for new member accounts.', 'subject' => 'Welcome to {{ ministry_name }}', 'body' => "Hello {{ member_name }},\n\nWelcome to {{ ministry_name }}. Your member account is ready.", 'variables' => ['member_name' => 'Member name', 'ministry_name' => 'Ministry name']],
            'giving_receipt' => ['name' => 'Giving receipt', 'description' => 'Confirmation after a verified donation.', 'subject' => 'Your giving receipt from {{ ministry_name }}', 'body' => "Hello {{ donor_name }},\n\nThank you for your gift of {{ amount }} to {{ fund_name }}. Your receipt reference is {{ receipt_reference }}.", 'variables' => ['donor_name' => 'Donor name', 'amount' => 'Formatted amount and currency', 'fund_name' => 'Donation purpose', 'receipt_reference' => 'Receipt reference', 'ministry_name' => 'Ministry name']],
            'event_confirmation' => ['name' => 'Event confirmation', 'description' => 'Confirmation for an event registration.', 'subject' => 'Registration confirmed for {{ event_name }}', 'body' => "Hello {{ member_name }},\n\nYour registration for {{ event_name }} on {{ event_date }} is confirmed.\n\nLocation: {{ location_name }}", 'variables' => ['member_name' => 'Registrant name', 'event_name' => 'Event name', 'event_date' => 'Formatted event date', 'location_name' => 'Event location', 'action_url' => 'Event link']],
            'follow_up_acknowledgement' => ['name' => 'Follow-up acknowledgement', 'description' => 'Acknowledges a connection or next-step request.', 'subject' => 'We received your {{ request_name }} request', 'body' => "Hello {{ member_name }},\n\nWe received your {{ request_name }} request. A ministry team member will follow up with you.", 'variables' => ['member_name' => 'Member name', 'request_name' => 'Request type']],
            'testimony_published' => ['name' => 'Testimony published', 'description' => 'Notifies a member after an approved testimony is published.', 'subject' => 'Your testimony has been published', 'body' => "Hello {{ member_name }},\n\nYour testimony, “{{ testimony_title }}”, has been reviewed and published.", 'variables' => ['member_name' => 'Member name', 'testimony_title' => 'Testimony title', 'action_url' => 'Public testimony link']],
        ];
    }

    public function templates(): Collection
    {
        $this->ensureDefaults();
        return DB::table('email_templates')->orderBy('name')->get()->map(function ($template) {
            $template->allowed_variables = json_decode($template->allowed_variables, true) ?: [];
            return $template;
        });
    }

    public function template(string $key): object
    {
        $this->ensureDefaults();
        $template = DB::table('email_templates')->where('key', $key)->first();
        abort_if($template === null, 404);
        $template->allowed_variables = json_decode($template->allowed_variables, true) ?: [];
        return $template;
    }

    public function update(string $key, array $data, User $actor, ?string $ip): void
    {
        $template = $this->template($key);
        $allowed = array_keys($template->allowed_variables);
        $this->validatePlaceholders($data['subject'], $allowed);
        $this->validatePlaceholders($data['body_template'], $allowed);
        DB::transaction(function () use ($template, $data, $actor, $ip): void {
            DB::table('email_templates')->where('id', $template->id)->update(['subject' => trim($data['subject']), 'body_template' => trim($data['body_template']), 'enabled' => (bool) ($data['enabled'] ?? false), 'updated_by' => $actor->id, 'updated_at' => now()]);
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => 'email_template.updated', 'subject_type' => 'email_templates', 'subject_id' => $template->id, 'changes_json' => json_encode(['key' => $template->key, 'enabled' => (bool) ($data['enabled'] ?? false)], JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now()]);
        });
    }

    public function reset(string $key, User $actor, ?string $ip): void
    {
        abort_unless(isset(self::catalog()[$key]), 404);
        $default = self::catalog()[$key];
        $this->update($key, ['subject' => $default['subject'], 'body_template' => $default['body'], 'enabled' => true], $actor, $ip);
    }

    /** @param array<string,string|int|float> $variables */
    public function send(string $key, string $recipient, array $variables, ?string $recipientName = null, ?string $actionText = null): void
    {
        $template = $this->template($key);
        if (! $template->enabled) {
            if (in_array($key, ['staff_email_verification', 'password_reset', 'staff_invitation'], true)) abort(503, 'This required account email template is inactive. Contact the ministry administrator.');
            return;
        }
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $variables['ministry_name'] ??= $settings->name;
        $subject = $this->replace($template->subject, $variables);
        $bodyVariables = $variables;
        if (isset($bodyVariables['action_url'])) $bodyVariables['action_url'] = 'the button below';
        $body = $this->replace($template->body_template, $bodyVariables);
        $html = view('emails.ministry-transactional', ['settings' => $settings, 'subjectLine' => $subject, 'heading' => $template->name, 'body' => $body, 'actionText' => $actionText, 'actionUrl' => $variables['action_url'] ?? null])->render();
        $this->delivery->sendHtml($recipient, $subject, $html, trim($template->name."\n\n".$body), $recipientName);
    }

    public function preview(string $key): string
    {
        $template = $this->template($key);
        $samples = collect($template->allowed_variables)->mapWithKeys(fn ($label, $name) => [$name => $name === 'action_url' ? '#' : match ($name) { 'expires_minutes' => '30', 'expires_hours' => '24', 'amount' => '100.00 USD', default => Str::headline($name) }])->all();
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $samples['ministry_name'] ??= $settings->name;
        return view('emails.ministry-transactional', ['settings' => $settings, 'subjectLine' => $this->replace($template->subject, $samples), 'heading' => $template->name, 'body' => $this->replace($template->body_template, array_merge($samples, ['action_url' => 'the button below'])), 'actionText' => isset($samples['action_url']) ? 'Continue securely' : null, 'actionUrl' => isset($samples['action_url']) ? '#' : null])->render();
    }

    public function sendTest(string $key, string $recipient): void
    {
        $template = $this->template($key);
        $samples = collect($template->allowed_variables)->mapWithKeys(fn ($label, $name) => [$name => $name === 'action_url' ? rtrim((string) config('app.url'), '/').'/' : match ($name) { 'expires_minutes' => '30', 'expires_hours' => '24', 'amount' => '100.00 USD', default => Str::headline($name) }])->all();
        $this->send($key, $recipient, $samples, 'Email administrator', isset($samples['action_url']) ? 'Open test destination' : null);
    }

    private function ensureDefaults(): void
    {
        foreach (self::catalog() as $key => $default) {
            if (! DB::table('email_templates')->where('key', $key)->exists()) {
                DB::table('email_templates')->insert(['id' => (string) Str::uuid(), 'key' => $key, 'name' => $default['name'], 'description' => $default['description'], 'subject' => $default['subject'], 'body_template' => $default['body'], 'allowed_variables' => json_encode($default['variables'], JSON_THROW_ON_ERROR), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function validatePlaceholders(string $value, array $allowed): void
    {
        preg_match_all('/{{\s*([a-z][a-z0-9_]*)\s*}}/i', $value, $matches);
        $unknown = array_values(array_diff(array_unique($matches[1]), $allowed));
        if ($unknown !== []) throw ValidationException::withMessages(['body_template' => 'Unsupported placeholder(s): '.implode(', ', array_map(fn ($item) => '{{ '.$item.' }}', $unknown)).'.']);
        if (preg_match('/{{|}}/', preg_replace('/{{\s*[a-z][a-z0-9_]*\s*}}/i', '', $value))) throw ValidationException::withMessages(['body_template' => 'A template placeholder is incomplete or malformed.']);
    }

    private function replace(string $value, array $variables): string
    {
        return (string) preg_replace_callback('/{{\s*([a-z][a-z0-9_]*)\s*}}/i', fn (array $match) => array_key_exists($match[1], $variables) ? (string) $variables[$match[1]] : $match[0], $value);
    }
}
