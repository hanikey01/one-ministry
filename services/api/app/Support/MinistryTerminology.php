<?php

namespace App\Support;

use App\Models\MinistrySetting;
use Illuminate\Http\Request;

final class MinistryTerminology
{
    /** @var array<string, array{singular: string, plural: string, description: string}> */
    public const TERMS = [
        'service' => ['singular' => 'Service', 'plural' => 'Services', 'description' => 'Scheduled worship and broadcast gatherings. This is separate from interactive meetings.'],
        'meeting' => ['singular' => 'Meeting', 'plural' => 'Meetings', 'description' => 'Smaller interactive rooms, such as classes or department gatherings.'],
        'location' => ['singular' => 'Location', 'plural' => 'Locations', 'description' => 'Ministry campuses, branches, and gathering places.'],
        'department' => ['singular' => 'Department', 'plural' => 'Departments', 'description' => 'Ministry departments and their teams.'],
        'person' => ['singular' => 'Person', 'plural' => 'People', 'description' => 'Individual records in the staff directory. This does not change a person’s relationship status.'],
        'member' => ['singular' => 'Member', 'plural' => 'Members', 'description' => 'People using the private member workspace.'],
        'event' => ['singular' => 'Event', 'plural' => 'Events', 'description' => 'Published gatherings with registration, capacity, and attendance workflows.'],
        'next_step' => ['singular' => 'Next step', 'plural' => 'Next steps', 'description' => 'Member connection requests that enter the staff follow-up workflow.'],
        'testimony' => ['singular' => 'Testimony', 'plural' => 'Testimonies', 'description' => 'Consent-based stories submitted for staff review and optional public publishing.'],
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
            ['/departments', 'Departments'] => $this->label('department', 'plural'),
            ['/locations', 'Locations'] => $this->label('location', 'plural'),
            ['/events', 'Events'] => $this->label('event', 'plural'),
            ['/testimonies', 'Testimonies'] => $this->label('testimony', 'plural'),
            ['/member/departments', 'My departments'] => 'My '.mb_strtolower($this->label('department', 'plural')),
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
