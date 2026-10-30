<?php

namespace App\Services;

use App\Models\FeatureSetting;
use App\Models\User;

class ModuleAvailability
{
    /**
     * Optional module areas whose routes must be unavailable when their module is off.
     * Keep these prefixes aligned with the ministry feature registry.
     *
     * @var array<string, list<string>>
     */
    private const ROUTE_PREFIXES = [
        'people' => [
            '/staff/people', '/api/v1/staff/people', '/staff/celebrations', '/api/v1/staff/celebrations',
            '/staff/follow-up', '/staff/follow-up-tasks', '/api/v1/staff/follow-up-tasks',
        ],
        'groups' => ['/staff/groups', '/api/v1/staff/groups', '/member/groups', '/api/v1/public/groups', '/public/groups', '/groups'],
        'giving' => ['/staff/giving', '/api/v1/staff/giving', '/member/giving', '/give', '/api/v1/me/donations', '/api/v1/public/giving', '/api/v1/webhooks/giving'],
        'testimonies' => ['/staff/testimonies','/member/testimonies','/testimonies'],
        'digital_church' => [
            '/staff/services', '/api/v1/staff/services', '/staff/service-schedule', '/api/v1/staff/service-schedule',
            '/staff/service-templates', '/api/v1/staff/service-templates', '/staff/recordings', '/api/v1/staff/recordings',
            '/worship', '/api/v1/public/services', '/api/v1/public/media', '/api/v1/webhooks/livestream',
        ],
        'interactive_meetings' => ['/staff/meetings', '/api/v1/staff/meetings', '/meetings', '/api/v1/meetings'],
        'digital_school' => ['/staff/school', '/api/v1/staff/school', '/member/school', '/api/v1/member/school'],
        'ai_page_editor' => ['/staff/pages/ai-draft', '/api/v1/staff/pages/ai-draft'],
    ];

    private const LIBRARY_KEYS = ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'];

    public function enabled(string $key): bool
    {
        return FeatureSetting::query()->where(['key' => $key, 'state' => 'enabled'])->exists();
    }

    public function visibleToStaff(string $key, ?User $user): bool
    {
        $state = FeatureSetting::query()->where('key', $key)->value('state');

        if ($state === 'enabled') {
            return true;
        }

        return $state === 'staff_preview'
            && $user !== null
            && app(PermissionChecker::class)->allows($user, 'settings.read');
    }

    public function requiredForPath(string $path): ?string
    {
        $path = '/'.ltrim($path, '/');

        foreach (self::ROUTE_PREFIXES as $key => $prefixes) {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $key;
                }
            }
        }

        if ($path === '/staff/libraries/books/upload' || $path === '/api/v1/staff/libraries/books/upload') {
            return 'books_library';
        }

        if (preg_match('#^/(?:api/v1/)?staff/libraries/([^/]+)#', $path, $matches)
            || preg_match('#^/api/v1/public/libraries/([^/]+)#', $path, $matches)
            || preg_match('#^/api/v1/public/sermon-series/([^/]+)#', $path, $matches)
            || preg_match('#^/(?:api/v1/)?resources/([^/]+)#', $path, $matches)) {
            return in_array($matches[1], self::LIBRARY_KEYS, true) ? $matches[1] : null;
        }

        return null;
    }
}
