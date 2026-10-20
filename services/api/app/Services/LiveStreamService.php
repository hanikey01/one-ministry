<?php

namespace App\Services;

use App\Models\ServiceOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LiveStreamService
{
    public function __construct(private LiveStreamProviderRegistry $providers) {}

    public function configure(ServiceOccurrence $service, array $input, User $actor): object
    {
        $playback = $this->providers->normalize($input['provider'], $input['playback_url']);
        $lowBandwidth = isset($input['lower_bandwidth_url']) ? $this->typedUrl($input['lower_bandwidth_url'], 'lower_bandwidth_url', '.m3u8') : null;
        $captions = isset($input['captions_url']) ? $this->typedUrl($input['captions_url'], 'captions_url', '.vtt') : null;
        $existing = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        $id = $existing?->id ?? (string) Str::uuid();

        DB::transaction(function () use ($service, $input, $actor, $playback, $lowBandwidth, $captions, $existing, $id): void {
            $values = [
                'provider' => $input['provider'], 'playback_type' => $playback['type'],
                'provider_stream_id' => $playback['reference'], 'playback_url' => $playback['url'],
                'lower_bandwidth_url' => $lowBandwidth, 'captions_url' => $captions,
                'fallback_message' => $input['fallback_message'] ?? null, 'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('live_sessions')->where('id', $id)->update($values);
            } else {
                DB::table('live_sessions')->insert([...$values, 'id' => $id, 'service_occurrence_id' => $service->id, 'status' => 'configured', 'ingest_health' => 'unknown', 'playback_health' => 'unknown', 'created_by' => $actor->id, 'created_at' => now()]);
            }
            $this->event($id, $actor->id, 'configuration_changed', ['provider' => $input['provider'], 'playback_type' => $playback['type']]);
        });

        return DB::table('live_sessions')->where('id', $id)->first();
    }

    public function updateHealth(object $session, string $ingest, string $playback, ?string $reason, ?User $actor, string $source = 'operator', ?string $externalEventId = null): object
    {
        DB::transaction(function () use ($session, $ingest, $playback, $reason, $actor, $source, $externalEventId): void {
            DB::table('live_sessions')->where('id', $session->id)->update(['ingest_health' => $ingest, 'playback_health' => $playback, 'last_health_at' => now(), 'updated_at' => now()]);
            $this->event($session->id, $actor?->id, 'health_changed', ['ingest_health' => $ingest, 'playback_health' => $playback, 'reason' => $reason], $source, $externalEventId);
            $service = DB::table('service_occurrences')->where('id', $session->service_occurrence_id)->first();
            if ($service?->state === 'live' && ($ingest === 'offline' || $playback === 'offline')) {
                DB::table('service_occurrences')->where('id', $service->id)->update(['state' => 'interrupted', 'updated_at' => now()]);
                DB::table('live_sessions')->where('id', $session->id)->update(['status' => 'interrupted', 'updated_at' => now()]);
                $this->event($session->id, $actor?->id, 'service_interrupted', ['reason' => $reason, 'trigger' => $source], $source);
            }
        });

        return DB::table('live_sessions')->where('id', $session->id)->first();
    }

    public function probe(object $session, User $actor): array
    {
        $result = $this->performProbe($session);
        $status = $result['status'];
        $httpStatus = $result['http_status'];
        $latency = $result['latency_ms'];
        DB::transaction(function () use ($session, $actor, $status, $httpStatus, $latency): void {
            DB::table('live_sessions')->where('id', $session->id)->update(['last_probe_status' => $status, 'last_probe_http_status' => $httpStatus, 'last_probe_latency_ms' => $latency, 'last_probed_at' => now(), 'playback_health' => $status, 'last_health_at' => now(), 'updated_at' => now()]);
            $this->event($session->id, $actor->id, 'playback_probed', ['status' => $status, 'http_status' => $httpStatus, 'latency_ms' => $latency], 'probe');
        });

        return [...$result, 'probed_at' => now()->toIso8601String()];
    }

    public function performProbe(object $session): array
    {
        $url = $this->providers->playbackUrl($session);
        $this->assertProbeTarget($url);
        $started = hrtime(true);
        try {
            $response = Http::timeout(8)->connectTimeout(4)->withHeaders(['Range' => 'bytes=0-4095', 'User-Agent' => 'OneMinistry-Playback-Probe/1.0'])->get($url);
            $httpStatus = $response->status();
            $healthy = $response->successful() || ($response->status() >= 300 && $response->status() < 400);
            if ($healthy && $session->playback_type === 'hls') {
                $healthy = str_contains($response->body(), '#EXTM3U');
            }
        } catch (\Throwable) {
            $httpStatus = null;
            $healthy = false;
        }
        $latency = max(1, (int) round((hrtime(true) - $started) / 1_000_000));
        $status = $healthy ? 'healthy' : 'offline';

        return ['status' => $status, 'http_status' => $httpStatus, 'latency_ms' => $latency];
    }

    public function usable(ServiceOccurrence $service): bool
    {
        return DB::table('live_sessions')->where('service_occurrence_id', $service->id)
            ->where('ingest_health', 'healthy')->whereIn('playback_health', ['healthy', 'degraded'])->exists();
    }

    public function publicDescriptor(ServiceOccurrence $service): array
    {
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        $fallback = $session?->fallback_message ?: 'The live video is currently unavailable. You can still use the service notes and connection options.';
        if (! $session || $service->state === 'interrupted' || ! in_array($session->playback_health, ['healthy', 'degraded'], true)) {
            return ['state' => 'unavailable', 'message' => $fallback, 'playback_type' => null, 'playback_url' => null, 'lower_bandwidth_url' => null, 'captions_url' => null];
        }
        $url = match ($session->playback_type) {
            'youtube' => "https://www.youtube-nocookie.com/embed/{$session->provider_stream_id}?autoplay=1&rel=0",
            'vimeo' => "https://player.vimeo.com/video/{$session->provider_stream_id}?autoplay=1",
            default => $session->playback_url,
        };

        return ['state' => $session->playback_health === 'degraded' ? 'degraded' : 'available', 'message' => $session->playback_health === 'degraded' ? 'Playback quality may be reduced.' : 'Live video is available.', 'playback_type' => $session->playback_type, 'playback_url' => $url, 'lower_bandwidth_url' => $session->lower_bandwidth_url, 'captions_url' => $session->captions_url];
    }

    private function secureUrl(string $value, string $field): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            throw ValidationException::withMessages([$field => 'Use a valid HTTPS URL.']);
        }

        return $value;
    }

    private function typedUrl(string $value, string $field, string $extension): string
    {
        $url = $this->secureUrl($value, $field);
        if (! str_contains(strtolower((string) parse_url($url, PHP_URL_PATH)), $extension)) {
            throw ValidationException::withMessages([$field => "The URL must point to a {$extension} file."]);
        }

        return $url;
    }

    public function assertProbeTarget(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw ValidationException::withMessages(['playback_url' => 'The playback probe requires a public HTTPS host.']);
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw ValidationException::withMessages(['playback_url' => 'Private or reserved network addresses cannot be probed.']);
            }

            return;
        }
        if (in_array($host, ['www.youtube-nocookie.com', 'player.vimeo.com'], true)) {
            return;
        }
        $addresses = array_filter(array_map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null, dns_get_record($host, DNS_A | DNS_AAAA) ?: []));
        if ($addresses === [] || collect($addresses)->contains(fn (string $ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw ValidationException::withMessages(['playback_url' => 'The playback host could not be verified as a public network address.']);
        }
    }

    private function event(string $session, ?string $actor, string $type, array $changes, string $source = 'operator', ?string $externalEventId = null): void
    {
        DB::table('live_stream_events')->insert(['id' => (string) Str::uuid(), 'live_session_id' => $session, 'actor_user_id' => $actor, 'event_type' => $type, 'source' => $source, 'external_event_id' => $externalEventId, 'changes_json' => json_encode($changes), 'created_at' => now()]);
    }
}
