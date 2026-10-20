<?php

namespace App\Services;

use App\Models\User;
use App\Models\ZoomVideoSdkSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ZoomVideoSdkSettingsService
{
    public function setting(): ZoomVideoSdkSetting
    {
        return ZoomVideoSdkSetting::query()->firstOrCreate([], ['id' => (string) Str::uuid(), 'enabled' => false]);
    }

    public function ready(): bool
    {
        $setting = $this->setting();
        return $setting->enabled && filled($setting->sdk_key) && filled($setting->sdk_secret_encrypted);
    }

    public function update(array $data, User $actor, ?string $ip): ZoomVideoSdkSetting
    {
        $setting = $this->setting();
        if (($data['enabled'] ?? false) && (blank($data['sdk_key'] ?? $setting->sdk_key) || blank($data['sdk_secret'] ?? null) && blank($setting->sdk_secret_encrypted))) {
            throw ValidationException::withMessages(['enabled' => 'Enter the Zoom Video SDK key and secret before activating meetings.']);
        }
        $changes = [
            'enabled' => (bool) ($data['enabled'] ?? false), 'sdk_key' => $data['sdk_key'] ?? $setting->sdk_key,
            'monthly_participant_minute_warning' => $data['monthly_participant_minute_warning'] ?? null,
            'monthly_participant_minute_limit' => $data['monthly_participant_minute_limit'] ?? null,
            'updated_by' => $actor->id,
        ];
        if (filled($data['sdk_secret'] ?? null)) $changes['sdk_secret_encrypted'] = Crypt::encryptString($data['sdk_secret']);
        if (filled($data['webhook_secret'] ?? null)) $changes['webhook_secret_encrypted'] = Crypt::encryptString($data['webhook_secret']);
        DB::transaction(function () use ($setting, $changes, $actor, $ip): void {
            $setting->update($changes);
            DB::table('audit_events')->insert(['id'=>(string) Str::uuid(), 'actor_user_id'=>$actor->id, 'action'=>'zoom_video_sdk.settings_updated', 'subject_type'=>'zoom_video_sdk_settings', 'subject_id'=>$setting->id, 'changes_json'=>json_encode(['enabled'=>$changes['enabled'], 'sdk_key_configured'=>filled($changes['sdk_key']), 'sdk_secret_replaced'=>array_key_exists('sdk_secret_encrypted',$changes), 'webhook_secret_replaced'=>array_key_exists('webhook_secret_encrypted',$changes)]), 'ip_address'=>$ip, 'created_at'=>now()]);
        });
        return $setting->fresh();
    }
}
