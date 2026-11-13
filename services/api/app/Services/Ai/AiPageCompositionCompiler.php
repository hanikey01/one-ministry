<?php

namespace App\Services\Ai;

use App\Services\PageDocumentService;
use App\Services\PageElementRegistry;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AiPageCompositionCompiler
{
    public function __construct(private readonly PageElementRegistry $registry, private readonly PageDocumentService $documents) {}

    public function compile(array $composition, array $features, array $approvedMedia = []): array
    {
        if (strlen(json_encode($composition, JSON_THROW_ON_ERROR)) > 100000) {
            $this->invalid('composition_too_large');
        }
        $this->keys($composition, ['title', 'seo_title', 'seo_description', 'sections']);
        $sections = $this->items($composition['sections'] ?? null, 12);
        $contracts = $this->registry->objectContracts();
        $catalogue = $this->registry->available($features);
        $count = 0;
        $document = ['version' => PageDocumentService::VERSION, 'sections' => []];
        foreach ($sections as $section) {
            $this->keys($section, ['purpose', 'design', 'responsive', 'media', 'columns']);
            $columns = $this->items($section['columns'] ?? null, 4);
            $compiledColumns = [];
            $weights = [];
            foreach ($columns as $column) {
                $weight = $column['weight'] ?? 1;
                if (! is_int($weight) || $weight < 1 || $weight > 12) {
                    $this->invalid('invalid_layout');
                }
                $weights[] = $weight;
            }
            foreach ($columns as $index => $column) {
                $this->keys($column, ['weight', 'design', 'responsive', 'media', 'elements']);
                $elements = [];
                foreach ($this->items($column['elements'] ?? null, 8) as $element) {
                    $this->keys($element, ['type', 'content', 'design', 'responsive', 'media']);
                    if (++$count > 40) {
                        $this->invalid('composition_too_large');
                    }
                    $type = $element['type'] ?? null;
                    if (! is_string($type) || ! isset($catalogue[$type])) {
                        $this->invalid('unknown_or_disabled_element');
                    }
                    $definition = $catalogue[$type];
                    $content = $this->pairs($element['content'] ?? [], $definition['fields']);
                    foreach ($content as $field => $value) {
                        $maximum = $definition['field_definitions'][$field]['validation']['max'] ?? 500;
                        if (mb_strlen($value) > $maximum) {
                            $this->invalid('content_too_large');
                        }
                        if ($this->urlField($field) && $value !== '') {
                            $this->safeLink($value);
                        }
                    }
                    // Defaults belong to Laravel; do not introduce sample factual claims.
                    foreach ($definition['fields'] as $field) {
                        if (! array_key_exists($field, $content)) {
                            $rules = $definition['field_definitions'][$field]['validation'];
                            $content[$field] = isset($rules['enum']) || ($rules['format'] ?? '') === 'integer' ? ($definition['defaults'][$field] ?? '') : '';
                        }
                    }
                    $settings = $this->pairs($element['design'] ?? [], $definition['settings']);
                    $this->resolveMedia($element['media'] ?? [], $content, $settings, $definition, $approvedMedia);
                    $elements[] = ['id' => (string) Str::uuid(), 'type' => $type, 'content' => $content, 'settings' => $settings, 'responsive' => $this->responsive($element['responsive'] ?? [], $definition['responsive'])];
                }
                $settings = $this->pairs($column['design'] ?? [], $contracts['column']['settings']);
                $empty = [];
                $this->resolveMedia($column['media'] ?? [], $empty, $settings, [], $approvedMedia);
                $responsive = $this->responsive($column['responsive'] ?? [], $contracts['column']['responsive']);
                $responsive['desktop'] = ['width' => 'custom', 'custom_width' => round(100 * $weights[$index] / array_sum($weights), 4).'%'] + ($responsive['desktop'] ?? []);
                $responsive['mobile'] = ['width' => 'full'] + ($responsive['mobile'] ?? []);
                $compiledColumns[] = ['id' => (string) Str::uuid(), 'settings' => $settings + ['element_gap' => '24px'], 'responsive' => $responsive, 'elements' => $elements];
            }
            $settings = $this->pairs($section['design'] ?? [], $contracts['section']['settings']);
            $empty = [];
            $this->resolveMedia($section['media'] ?? [], $empty, $settings, [], $approvedMedia);
            $responsive = $this->responsive($section['responsive'] ?? [], $contracts['section']['responsive']);
            $responsive['mobile'] = ($responsive['mobile'] ?? []) + ['stack' => 'stack'];
            $document['sections'][] = ['id' => (string) Str::uuid(), 'type' => 'section', 'settings' => $settings + ['content_width' => 'standard', 'column_gap' => 'regular', 'padding' => 'regular'], 'responsive' => $responsive, 'columns' => $compiledColumns];
        }
        $result = [];
        foreach (['title' => 200, 'seo_title' => 70, 'seo_description' => 160] as $field => $limit) {
            if (! is_string($composition[$field] ?? null) || mb_strlen($composition[$field]) > $limit) {
                $this->invalid('invalid_page_metadata');
            }
            $result[$field] = trim(strip_tags($composition[$field]));
        }
        if ($result['title'] === '') {
            $this->invalid('invalid_page_metadata');
        }
        $result['document'] = $this->documents->normalise($document, $features);

        return $result;
    }

    public function compileScoped(array $proposal, string $kind, array $features, array $media): array
    {
        $column = ['weight' => 1, 'elements' => [$proposal]];
        $section = ['purpose' => 'Scoped revision', 'columns' => [$kind === 'column' ? $proposal : $column]];
        $composition = ['title' => 'Scoped revision', 'seo_title' => '', 'seo_description' => '', 'sections' => [$kind === 'section' ? $proposal : $section]];
        $document = $this->compile($composition, $features, $media)['document'];

        return match ($kind) {
            'section' => $document['sections'][0], 'column' => $document['sections'][0]['columns'][0], default => $document['sections'][0]['columns'][0]['elements'][0]
        };
    }

    private function keys(mixed $object, array $allowed): void
    {
        if (! is_array($object) || array_diff(array_keys($object), $allowed) !== []) {
            $this->invalid('invalid_composition_shape');
        }
    }

    private function items(mixed $items, int $maximum, int $minimum = 1): array
    {
        if (! is_array($items) || ! array_is_list($items) || count($items) < $minimum || count($items) > $maximum) {
            $this->invalid('invalid_composition_size');
        }

        return $items;
    }

    private function pairs(mixed $pairs, array $allowed): array
    {
        $result = [];
        foreach ($this->items($pairs, 80, 0) as $pair) {
            $this->keys($pair, ['field', 'value']);
            $field = $pair['field'] ?? null;
            $value = $pair['value'] ?? null;
            $side = is_string($field) && preg_match('/^(padding|margin)_(top|right|bottom|left)$/', $field, $matches) ? $matches : null;
            if (! is_string($field) || (! in_array($field, $allowed, true) && ! ($side && in_array($side[1], $allowed, true))) || isset($result[$field]) || ! is_string($value)) {
                $this->invalid('invalid_composition_field');
            }
            if (mb_strlen($value) > 12000) {
                $this->invalid('composition_too_large');
            }
            if ($field === 'custom_css' && (mb_strlen($value) > 4000 || preg_match('/(?:@|url\s*\(|expression\s*\(|<|>|\\\\)/i', $value))) {
                $this->invalid('unsafe_scoped_css');
            }
            if ($field === 'custom_css' && $value !== '' && preg_match('/(?:^|\})\s*(?!selector\b)[^{}]+\{/', $value)) {
                $this->invalid('unsafe_scoped_css');
            }
            if ($field === 'custom_css') {
                preg_match_all('/(?:^|\})\s*([^{}]+)\{/', $value, $rules);
                foreach ($rules[1] as $selectors) {
                    foreach (preg_split('/,(?![^(]*\))/', $selectors) as $selector) {
                        if (! preg_match('/^\s*selector(?:\b|[:.#\[])/', $selector)) {
                            $this->invalid('unsafe_scoped_css');
                        }
                    }
                }
            }
            if ($field === 'background_image' && $value !== '') {
                $this->invalid('media_reference_required');
            }
            if (in_array($field, ['slides', 'images'], true) && $value !== '') {
                $this->invalid('media_reference_required');
            }
            $result[$field] = $value;
        }
        foreach (['padding', 'margin'] as $box) {
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $key = $box.'_'.$side;
                if (isset($result[$key])) {
                    if (isset($result[$box]) && ! is_array($result[$box])) {
                        $this->invalid('conflicting_spacing');
                    }
                    $result[$box][$side] = $result[$key];
                    unset($result[$key]);
                }
            }
        }

        return $result;
    }

    private function responsive(mixed $entries, array $allowed): array
    {
        $result = [];
        foreach ($this->items($entries, 3, 0) as $entry) {
            $this->keys($entry, ['breakpoint', 'values']);
            $breakpoint = $entry['breakpoint'] ?? '';
            if (! in_array($breakpoint, ['desktop', 'tablet', 'mobile'], true) || isset($result[$breakpoint])) {
                $this->invalid('invalid_responsive_intent');
            }
            $result[$breakpoint] = $this->pairs($entry['values'] ?? [], $allowed);
        }

        return $result;
    }

    private function resolveMedia(mixed $references, array &$content, array &$settings, array $definition, array $approved): void
    {
        foreach ($this->items($references, 12, 0) as $reference) {
            $this->keys($reference, ['field', 'id']);
            $field = $reference['field'] ?? '';
            $id = $reference['id'] ?? '';
            if (! is_string($id) || ! isset($approved[$id])) {
                $this->invalid('invalid_media_reference');
            }
            $record = $approved[$id];
            if ($field === 'background_image') {
                if (($record['kind'] ?? '') !== 'image') {
                    $this->invalid('incompatible_media');
                }
                $settings['background_image'] = $record['url'];
            } elseif (isset($definition['field_definitions'][$field]) && ($definition['field_definitions'][$field]['media'] ?? false)) {
                $types = $definition['media']['types'] ?? [];
                if (str_ends_with($field, '_media_url') && isset($definition['fields']) && in_array('slide_one_type', $definition['fields'], true)) {
                    $types = ['image', 'video'];
                }
                if (! in_array($record['kind'] ?? '', $types, true)) {
                    $this->invalid('incompatible_media');
                }
                $content[$field] = $field === 'images' && ($content[$field] ?? '') !== '' ? $content[$field]."\n".$record['url'] : $record['url'];
            } else {
                $this->invalid('invalid_media_field');
            }
        }
        foreach ($definition['field_definitions'] ?? [] as $field => $rules) {
            if (($rules['media'] ?? false) && ($content[$field] ?? '') !== '') {
                foreach (explode("\n", $content[$field]) as $url) {
                    if (! in_array($url, array_column($approved, 'url'), true)) {
                        $this->invalid('media_reference_required');
                    }
                }
            }
        }
    }

    private function urlField(string $field): bool
    {
        return $field === 'url' || str_ends_with($field, '_url');
    }

    private function safeLink(string $url): void
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url) || str_starts_with($url, '//')) {
            $this->invalid('unsafe_link');
        }
        if (str_starts_with($url, '/')) {
            return;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true) || parse_url($url, PHP_URL_USER) !== null) {
            $this->invalid('unsafe_link');
        }
    }

    private function invalid(string $code): never
    {
        throw ValidationException::withMessages(['composition' => $code]);
    }
}
