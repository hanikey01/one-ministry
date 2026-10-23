<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Boundary for the versioned visual page document.
 *
 * The current editor still consumes the legacy flat block array. This service
 * gives the migration a single place to validate and flatten v2 documents
 * until the recursive renderer is enabled in the next editor batch.
 */
final class PageDocumentService
{
    /** @return array{version: 2, sections: array<int, array<string, mixed>>} */
    public function normalise(array $document): array
    {
        if (($document['version'] ?? null) !== 2 || ! is_array($document['sections'] ?? null)) {
            throw ValidationException::withMessages(['blocks' => 'The page document version is not supported.']);
        }

        if (count($document['sections']) > 60) {
            throw ValidationException::withMessages(['blocks' => 'A page can contain up to 60 sections.']);
        }

        $sections = [];
        foreach ($document['sections'] as $section) {
            if (! is_array($section)) {
                throw ValidationException::withMessages(['blocks' => 'Each page section must be an object.']);
            }

            $columns = $section['columns'] ?? null;
            if (! is_array($columns) || $columns === [] || count($columns) > 12) {
                throw ValidationException::withMessages(['blocks' => 'Each section must contain between one and twelve columns.']);
            }

            $sectionSettings = $this->settings($section['settings'] ?? [], 200);
            $responsive = $this->responsive($section['responsive'] ?? []);
            $normalisedColumns = [];
            foreach ($columns as $column) {
                if (! is_array($column)) {
                    throw ValidationException::withMessages(['blocks' => 'Each section column must be an object.']);
                }
                $elements = $column['elements'] ?? null;
                if (! is_array($elements) || count($elements) > 60) {
                    throw ValidationException::withMessages(['blocks' => 'Each column must contain up to 60 elements.']);
                }
                $normalisedElements = [];
                foreach ($elements as $element) {
                    if (! is_array($element) || ! is_string($element['type'] ?? null)) {
                        throw ValidationException::withMessages(['blocks' => 'Each page element must have a type.']);
                    }
                    $normalisedElements[] = [
                        'id' => $this->id($element['id'] ?? null),
                        'type' => Str::lower(trim($element['type'])),
                        'content' => $this->settings($element['content'] ?? [], 500),
                        'settings' => $this->settings($element['settings'] ?? [], 200),
                        'responsive' => $this->responsive($element['responsive'] ?? []),
                    ];
                }
                $normalisedColumns[] = [
                    'id' => $this->id($column['id'] ?? null),
                    'settings' => $this->settings($column['settings'] ?? [], 200),
                    'responsive' => $this->responsive($column['responsive'] ?? []),
                    'elements' => $normalisedElements,
                ];
            }

            $sections[] = [
                'id' => $this->id($section['id'] ?? null),
                'type' => 'section',
                'settings' => $sectionSettings,
                'responsive' => $responsive,
                'columns' => $normalisedColumns,
            ];
        }

        return ['version' => 2, 'sections' => $sections];
    }

    /** @return array<int, array{type: string, data: array<string, string>}> */
    public function flatten(array $document): array
    {
        $flat = [];
        foreach ($this->normalise($document)['sections'] as $section) {
            $columns = $section['columns'];
            if (count($columns) > 1) {
                $flat[] = ['type' => 'section', 'data' => [
                    'layout' => count($columns) >= 3 ? 'three' : 'two',
                    'title' => (string) ($section['settings']['title'] ?? ''),
                    'body' => (string) ($section['settings']['body'] ?? ''),
                    'background_color' => (string) ($section['settings']['background_color'] ?? ''),
                    'padding' => (string) ($section['settings']['padding'] ?? 'regular'),
                ]];
            }
            foreach ($columns as $column) {
                foreach ($column['elements'] as $element) {
                    $flat[] = ['type' => $element['type'], 'data' => array_merge($element['content'], $element['settings'])];
                }
            }
        }
        return $flat;
    }

    /** Convert the current editor's flat blocks into the versioned document. */
    public function fromFlat(array $blocks): array
    {
        $sections = [];
        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? Str::lower(trim($block['type'])) : 'paragraph';
            $content = is_array($block['data'] ?? null) ? $block['data'] : [];
            $sections[] = [
                'id' => 'section-'.($index + 1),
                'type' => 'section',
                'settings' => [],
                'responsive' => [],
                'columns' => [[
                    'id' => 'column-'.($index + 1),
                    'settings' => [],
                    'responsive' => [],
                    'elements' => [[
                        'id' => 'element-'.($index + 1),
                        'type' => $type,
                        'content' => $content,
                        'settings' => [],
                        'responsive' => [],
                    ]],
                ]],
            ];
        }

        return $this->normalise(['version' => 2, 'sections' => $sections]);
    }

    /** @param array<string, mixed> $settings */
    private function settings(array $settings, int $max): array
    {
        $result = [];
        foreach ($settings as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                throw ValidationException::withMessages(['blocks' => 'Page settings must contain scalar values.']);
            }
            $result[Str::snake($key)] = Str::limit(trim((string) $value), $max);
        }
        return $result;
    }

    /** @param array<string, mixed> $responsive */
    private function responsive(array $responsive): array
    {
        $result = [];
        foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
            if (array_key_exists($breakpoint, $responsive)) {
                if (! is_array($responsive[$breakpoint])) {
                    throw ValidationException::withMessages(['blocks' => 'Responsive settings must be grouped by breakpoint.']);
                }
                $result[$breakpoint] = $this->settings($responsive[$breakpoint], 120);
            }
        }
        return $result;
    }

    private function id(mixed $id): string
    {
        return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id) ? $id : (string) Str::uuid();
    }
}
