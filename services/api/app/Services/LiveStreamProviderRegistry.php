<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class LiveStreamProviderRegistry
{
    public function normalize(string $provider, string $value): array
    {
        if ($provider === 'youtube') {
            preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|live/))([A-Za-z0-9_-]{6,20})~', $value, $match);
            if (! isset($match[1])) {
                throw ValidationException::withMessages(['playback_url' => 'Enter a valid YouTube video or live URL.']);
            }

            return ['type' => 'youtube', 'reference' => $match[1], 'url' => null];
        }
        if ($provider === 'vimeo') {
            preg_match('~vimeo\.com/(?:video/)?([0-9]{6,12})~', $value, $match);
            if (! isset($match[1])) {
                throw ValidationException::withMessages(['playback_url' => 'Enter a valid Vimeo video URL.']);
            }

            return ['type' => 'vimeo', 'reference' => $match[1], 'url' => null];
        }
        $url = $this->secureUrl($value, 'playback_url');
        if (! str_contains(strtolower(parse_url($url, PHP_URL_PATH) ?: ''), '.m3u8')) {
            throw ValidationException::withMessages(['playback_url' => 'The HLS playback URL must point to an .m3u8 manifest.']);
        }

        return ['type' => 'hls', 'reference' => null, 'url' => $url];
    }

    public function capabilities(string $provider): array
    {
        return match ($provider) {
            'youtube', 'vimeo' => ['embedded_playback' => true, 'manifest_probe' => false, 'lower_bandwidth_rendition' => true, 'provider_health_callback' => true, 'ingest_credentials_managed_here' => false],
            'hls' => ['embedded_playback' => false, 'manifest_probe' => true, 'lower_bandwidth_rendition' => true, 'provider_health_callback' => true, 'ingest_credentials_managed_here' => false],
            default => throw ValidationException::withMessages(['provider' => 'Unsupported livestream provider.']),
        };
    }

    public function playbackUrl(object $session): string
    {
        return match ($session->playback_type) {
            'youtube' => "https://www.youtube-nocookie.com/embed/{$session->provider_stream_id}",
            'vimeo' => "https://player.vimeo.com/video/{$session->provider_stream_id}",
            default => $session->playback_url,
        };
    }

    public function secureUrl(string $value, string $field): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            throw ValidationException::withMessages([$field => 'Use a valid HTTPS URL.']);
        }

        return $value;
    }
}
