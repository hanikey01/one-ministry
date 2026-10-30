<?php

namespace App\Services;

use App\Models\PlatformUpdateSetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformUpdateService
{
    public function setting(): PlatformUpdateSetting
    {
        return PlatformUpdateSetting::query()->first() ?? PlatformUpdateSetting::query()->create([
            'id' => (string) Str::uuid(),
            'current_version' => (string) config('app.version', 'dev'),
        ]);
    }

    public function updateConfiguration(array $data, User $actor, ?string $ip): PlatformUpdateSetting
    {
        $setting = $this->setting();
        $changes = [
            'channel' => $data['channel'],
            'mode' => $data['mode'],
            'repository' => trim((string) ($data['repository'] ?? '')) ?: null,
            'signing_key_id' => trim((string) ($data['signing_key_id'] ?? '')) ?: null,
            'public_key_pem' => trim((string) ($data['public_key_pem'] ?? '')) ?: null,
            'updated_by' => $actor->id,
            'updated_at' => now(),
        ];
        if (filled($data['access_token'] ?? null)) {
            $changes['access_token_encrypted'] = Crypt::encryptString($data['access_token']);
        }
        $setting->update($changes);
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => 'platform_updates.configuration_updated', 'subject_type' => 'platform_update_settings', 'subject_id' => $setting->id, 'changes_json' => json_encode(['channel' => $setting->channel, 'mode' => $setting->mode, 'repository' => $setting->repository, 'token_replaced' => array_key_exists('access_token_encrypted', $changes), 'public_key_configured' => filled($setting->public_key_pem)], JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now()]);

        return $setting->fresh();
    }

    public function check(): PlatformUpdateSetting
    {
        $setting = $this->setting();
        $setting->update(['last_checked_at' => now(), 'status' => 'checking', 'last_error' => null]);
        try {
            if (! $setting->repository) throw new RuntimeException('Configure the private GitHub repository first.');
            [$owner, $repo] = array_pad(explode('/', trim($setting->repository, '/')), 2, null);
            if (! $owner || ! $repo) throw new RuntimeException('Repository must use the owner/repository format.');
            $token = $setting->access_token_encrypted ? Crypt::decryptString($setting->access_token_encrypted) : null;
            $response = Http::acceptJson()->withToken($token)->timeout(15)->get("https://api.github.com/repos/{$owner}/{$repo}/releases");
            if ($response->failed()) throw new RuntimeException('GitHub release metadata could not be read.');
            $release = collect($response->json())->first(fn (array $item) => $setting->channel === 'preview' || ! $item['prerelease']);
            if (! $release) throw new RuntimeException('No matching release was found for the selected channel.');
            $setting->update(['available_version' => $release['tag_name'], 'available_channel' => $release['prerelease'] ? 'preview' : 'stable', 'status' => version_compare(ltrim($release['tag_name'], 'v'), ltrim($setting->current_version, 'v'), '>') ? 'update_available' : 'current', 'last_checked_at' => now()]);
        } catch (\Throwable $exception) {
            $setting->update(['status' => 'error', 'last_error' => Str::limit($exception->getMessage(), 500), 'last_checked_at' => now()]);
        }
        return $setting->fresh();
    }
}
