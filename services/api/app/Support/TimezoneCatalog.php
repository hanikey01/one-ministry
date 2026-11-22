<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class TimezoneCatalog
{
    private const REGIONS = ['Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific'];

    /** @return array<string, array<int, array{value: string, label: string, search: string}>> */
    public static function groups(): array
    {
        $groups = ['Universal time' => [[
            'value' => 'UTC',
            'label' => 'UTC — Coordinated Universal Time',
            'search' => 'UTC Coordinated Universal Time GMT',
        ]]];
        $now = new DateTimeImmutable('now');

        foreach (self::REGIONS as $region) {
            $groups[$region] = [];
        }

        foreach (timezone_identifiers_list(DateTimeZone::ALL) as $identifier) {
            [$region] = explode('/', $identifier, 2);
            if (! in_array($region, self::REGIONS, true)) {
                continue;
            }

            $location = str_replace(['_', '/'], [' ', ' / '], substr($identifier, strlen($region) + 1));
            $offset = (new DateTimeZone($identifier))->getOffset($now);
            $sign = $offset < 0 ? '-' : '+';
            $absolute = abs($offset);
            $offsetLabel = sprintf('UTC%s%02d:%02d', $sign, intdiv($absolute, 3600), intdiv($absolute % 3600, 60));
            $groups[$region][] = [
                'value' => $identifier,
                'label' => "{$location} — {$region} ({$offsetLabel})",
                'search' => "{$identifier} {$location} {$region} {$offsetLabel}",
            ];
        }

        return array_filter($groups);
    }
}
