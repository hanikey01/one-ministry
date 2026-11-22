<?php

namespace App\Support;

class AddressCatalog
{
    /** @var array<string, string>|null */
    private ?array $countryList = null;

    /** @var array<string, array<string, string>> */
    private array $subdivisionLists = [];

    /** @return array<string, string> */
    public function countries(): array
    {
        if ($this->countryList !== null) {
            return $this->countryList;
        }

        $countries = $this->readJson($this->catalogPath('countries.en.json'));
        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->countryList = $countries;
    }

    /** @return array<string, string> */
    public function subdivisions(string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));
        if (! array_key_exists($countryCode, $this->countries())) {
            return [];
        }

        if (array_key_exists($countryCode, $this->subdivisionLists)) {
            return $this->subdivisionLists[$countryCode];
        }

        $path = $this->catalogPath('subdivisions/'.$countryCode.'.json');
        if (! is_file($path)) {
            return $this->subdivisionLists[$countryCode] = [];
        }

        $definitions = $this->readJson($path)['subdivisions'] ?? [];
        $subdivisions = [];
        foreach ($definitions as $code => $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $name = $definition['name'] ?? $definition['local_name'] ?? (string) $code;
            if (is_string($name) && $name !== '') {
                $subdivisions[(string) $code] = $name;
            }
        }
        asort($subdivisions, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->subdivisionLists[$countryCode] = $subdivisions;
    }

    public function countryName(?string $countryCode): ?string
    {
        if (blank($countryCode)) {
            return null;
        }

        return $this->countries()[strtoupper((string) $countryCode)] ?? null;
    }

    public function subdivisionName(?string $countryCode, ?string $subdivisionCode): ?string
    {
        if (blank($countryCode) || blank($subdivisionCode)) {
            return null;
        }

        return $this->subdivisions((string) $countryCode)[(string) $subdivisionCode] ?? null;
    }

    /**
     * @param  array{address_line_1?: ?string, address_line_2?: ?string, locality?: ?string, region_name?: ?string, postal_code?: ?string, country_name?: ?string}  $address
     */
    public function format(array $address): ?string
    {
        $locality = collect([
            $address['locality'] ?? null,
            $address['region_name'] ?? null,
            $address['postal_code'] ?? null,
        ])->filter(fn ($value) => filled($value))->join(', ');

        $formatted = collect([
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            $locality ?: null,
            $address['country_name'] ?? null,
        ])->filter(fn ($value) => filled($value))->join("\n");

        return $formatted !== '' ? $formatted : null;
    }

    private function catalogPath(string $path): string
    {
        return dirname(__DIR__, 2).'/resources/addressing/'.$path;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }
}
