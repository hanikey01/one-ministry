<?php

namespace App\Services;

use App\Models\PlatformUpdateSetting;
use App\Models\User;
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
            'mode' => 'manual',
            'updated_by' => $actor->id,
            'updated_at' => now(),
        ];
        $setting->update($changes);
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'action' => 'platform_updates.configuration_updated', 'subject_type' => 'platform_update_settings', 'subject_id' => $setting->id, 'changes_json' => json_encode(['channel' => $setting->channel, 'mode' => $setting->mode], JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now()]);

        return $setting->fresh();
    }

    public function check(): PlatformUpdateSetting
    {
        $setting = $this->setting();
        $setting->update(['last_checked_at' => now(), 'status' => 'checking', 'last_error' => null]);
        try {
            [$owner, $repo] = array_pad(explode('/', trim((string) config('platform_updates.repository'), '/')), 2, null);
            if (! $owner || ! $repo) throw new RuntimeException('Repository must use the owner/repository format.');
            $response = Http::acceptJson()->timeout(15)->get("https://api.github.com/repos/{$owner}/{$repo}/releases");
            if ($response->failed()) throw new RuntimeException('GitHub release metadata could not be read.');
            $release = collect($response->json())->first(fn (array $item) => ! ($item['draft'] ?? false) && ($setting->channel === 'preview' || ! ($item['prerelease'] ?? false)));
            if (! $release) throw new RuntimeException('No matching release was found for the selected channel.');
            $this->verifyReleaseManifest($release);
            $setting->update(['available_version' => $release['tag_name'], 'available_channel' => $release['prerelease'] ? 'preview' : 'stable', 'status' => version_compare(ltrim($release['tag_name'], 'v'), ltrim($setting->current_version, 'v'), '>') ? 'update_available' : 'current', 'last_checked_at' => now()]);
        } catch (\Throwable $exception) {
            $setting->update(['status' => 'error', 'last_error' => Str::limit($exception->getMessage(), 500), 'last_checked_at' => now()]);
        }
        return $setting->fresh();
    }

    /**
     * Verify the signed metadata before an installation ever treats a GitHub
     * release as an available One Ministry update. Package download and
     * activation will consume this same manifest in the installer phase.
     *
     * @param array<string, mixed> $release
     */
    private function verifyReleaseManifest(array $release): void
    {
        $assets = collect($release['assets'] ?? [])->keyBy('name');
        $manifestAsset = $assets->get('release-manifest.json');
        $signatureAsset = $assets->get('release-manifest.sig.b64');
        if (! is_array($manifestAsset) || ! is_array($signatureAsset)) {
            throw new RuntimeException('The release does not include the required signed update manifest.');
        }

        $manifestResponse = Http::timeout(20)->get($manifestAsset['browser_download_url'] ?? '');
        $signatureResponse = Http::timeout(20)->get($signatureAsset['browser_download_url'] ?? '');
        if ($manifestResponse->failed() || $signatureResponse->failed()) {
            throw new RuntimeException('The signed update manifest could not be downloaded.');
        }

        $manifestJson = $manifestResponse->body();
        $signature = base64_decode(trim($signatureResponse->body()), true);
        $publicKey = (string) config('platform_updates.public_key_pem');
        if ($signature === false || $publicKey === '' || openssl_verify($manifestJson, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('The release signature could not be verified.');
        }

        try {
            $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('The signed update manifest is invalid.');
        }

        if (! is_array($manifest)
            || ($manifest['schema_version'] ?? null) !== 1
            || ($manifest['product'] ?? null) !== 'one-ministry'
            || ($manifest['version'] ?? null) !== ($release['tag_name'] ?? null)
            || ($manifest['signing_key_id'] ?? null) !== config('platform_updates.signing_key_id')
            || ! is_string($manifest['package'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/i', (string) ($manifest['sha256'] ?? ''))
            || ! $assets->has($manifest['package'])) {
            throw new RuntimeException('The signed update manifest does not describe a trusted One Ministry package.');
        }
    }
}
