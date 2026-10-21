<?php

namespace App\Support;

use App\Models\MinistrySetting;
use Illuminate\Http\Request;

final class MinistryTerminology
{
    /** @var array<string, array{singular: string, plural: string, description: string}> */
    public const TERMS = [
        'service' => ['singular' => 'Service', 'plural' => 'Services', 'description' => 'Scheduled worship and broadcast gatherings. This is separate from interactive meetings.'],
        'meeting' => ['singular' => 'Meeting', 'plural' => 'Meetings', 'description' => 'Smaller interactive rooms, such as classes or group gatherings.'],
        'group' => ['singular' => 'Group', 'plural' => 'Groups', 'description' => 'Small groups and ongoing community gatherings.'],
        'location' => ['singular' => 'Location', 'plural' => 'Locations', 'description' => 'Ministry campuses, branches, and gathering places.'],
        'volunteer' => ['singular' => 'Volunteer', 'plural' => 'Volunteers', 'description' => 'People serving on ministry teams.'],
        'department' => ['singular' => 'Department', 'plural' => 'Departments', 'description' => 'Ministry departments and their teams.'],
        'member' => ['singular' => 'Member', 'plural' => 'Members', 'description' => 'People using the member community area.'],
    ];

    private const REQUEST_CACHE_KEY = 'ministry_terminology_overrides';

    /** @return array<string, array{singular: string, plural: string, description: string}> */
    public function definitions(): array
    {
        return self::TERMS;
    }

    /** @return array<string, array{singular: string, plural: string}> */
    public function values(?MinistrySetting $settings = null): array
    {
        $overrides = $settings?->terminology ?? $this->overrides();
        $values = [];

        foreach (self::TERMS as $key => $definition) {
            $values[$key] = [
                'singular' => $overrides[$key]['singular'] ?? $definition['singular'],
                'plural' => $overrides[$key]['plural'] ?? $definition['plural'],
            ];
        }

        return $values;
    }

    public function label(string $term, string $form = 'singular'): string
    {
        abort_unless(isset(self::TERMS[$term]) && in_array($form, ['singular', 'plural'], true), 500);

        return $this->values()[$term][$form];
    }

    public function navigationLabel(string $url, string $label): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return match ([$path, $label]) {
            ['/groups', 'Groups'] => $this->label('group', 'plural'),
            ['/locations', 'Locations'] => $this->label('location', 'plural'),
            ['/member/groups', 'My groups'] => 'My '.mb_strtolower($this->label('group', 'plural')),
            default => $label,
        };
    }

    /** @return array<string, array<string, string>> */
    private function overrides(): array
    {
        $request = app()->bound('request') ? app(Request::class) : null;

        if (! $request) {
            return MinistrySetting::query()->find(MinistrySetting::SINGLETON_ID)?->terminology ?? [];
        }

        if (! $request->attributes->has(self::REQUEST_CACHE_KEY)) {
            $request->attributes->set(
                self::REQUEST_CACHE_KEY,
                MinistrySetting::query()->find(MinistrySetting::SINGLETON_ID)?->terminology ?? [],
            );
        }

        return $request->attributes->get(self::REQUEST_CACHE_KEY);
    }
}
