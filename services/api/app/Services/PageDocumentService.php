<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Validates and upgrades the portable, versioned public-page document. */
final class PageDocumentService
{
    public const VERSION = 2;
    private const MAX_SECTIONS = 60;
    private const MAX_COLUMNS = 12;
    private const MAX_ELEMENTS_PER_COLUMN = 60;
    private const MAX_ELEMENTS = 360;

    public function __construct(private readonly PageElementRegistry $elements) {}

    /** @param array<int, string> $enabledFeatures
     * @return array{version: 2, sections: array<int, array<string, mixed>>} */
    public function normalise(array $document, array $enabledFeatures = []): array
    {
        if (($document['version'] ?? null) !== self::VERSION || ! is_array($document['sections'] ?? null)) $this->invalid('The page document version is not supported.');
        if (count($document['sections']) > self::MAX_SECTIONS) $this->invalid('A page can contain up to 60 sections.');
        $contracts=$this->elements->objectContracts();
        $ids=[];
        $stableId=function(mixed $candidate) use (&$ids): string { $id=$this->id($candidate); if(isset($ids[$id])) $this->invalid('Every section, column, and element must have a unique stable ID.'); $ids[$id]=true; return $id; };
        $sections = []; $elementCount = 0;
        foreach ($document['sections'] as $section) {
            if (! is_array($section) || ($section['type'] ?? 'section') !== 'section') $this->invalid('Each page section must be a section object.');
            $columns = $section['columns'] ?? null;
            if (! is_array($columns) || $columns === [] || count($columns) > self::MAX_COLUMNS) $this->invalid('Each section must contain between one and twelve columns.');
            $normalisedColumns = [];
            foreach ($columns as $column) {
                if (! is_array($column)) $this->invalid('Each section column must be an object.');
                $elements = $column['elements'] ?? null;
                if (! is_array($elements) || count($elements) > self::MAX_ELEMENTS_PER_COLUMN) $this->invalid('Each column must contain up to 60 elements.');
                $normalisedElements = [];
                foreach ($elements as $element) {
                    if (! is_array($element) || ! is_string($element['type'] ?? null)) $this->invalid('Each page element must have a type.');
                    if (++$elementCount > self::MAX_ELEMENTS) $this->invalid('A page can contain up to 360 elements.');
                    $type = Str::lower(trim($element['type']));
                    if (! $this->elements->supports($type)) $this->invalid('The page contains an unsupported element.');
                    if (($feature = $this->elements->feature($type)) !== null && ! in_array($feature, $enabledFeatures, true)) $this->invalid('The page contains an element from a disabled module.');
                    $definition = $this->elements->definition($type);
                    $normalisedElements[] = ['id' => $stableId($element['id'] ?? null), 'type' => $type, 'content' => $this->content($type, $element['content'] ?? []), 'settings' => $this->settings($element['settings'] ?? [], 200, $definition['settings'] ?? []), 'responsive' => $this->responsive($element['responsive'] ?? [], $definition['responsive'] ?? [])];
                }
                $normalisedColumns[] = ['id' => $stableId($column['id'] ?? null), 'settings' => $this->settings($column['settings'] ?? [], 200, $contracts['column']['settings']), 'responsive' => $this->responsive($column['responsive'] ?? [], $contracts['column']['responsive']), 'elements' => $normalisedElements];
            }
            $sections[] = ['id' => $stableId($section['id'] ?? null), 'type' => 'section', 'settings' => $this->settings($section['settings'] ?? [], 200, $contracts['section']['settings']), 'responsive' => $this->responsive($section['responsive'] ?? [], $contracts['section']['responsive']), 'columns' => $normalisedColumns];
        }
        return ['version' => self::VERSION, 'sections' => $sections];
    }

    /** Build the compatibility/search summary directly from a canonical document. */
    public function plainText(array $document, array $enabledFeatures = []): string
    {
        $parts = [];
        foreach ($this->normalise($document, $enabledFeatures)['sections'] as $section) {
            foreach (['title', 'body'] as $field) if (filled($section['settings'][$field] ?? null)) $parts[] = $section['settings'][$field];
            foreach ($section['columns'] as $column) foreach ($column['elements'] as $element) {
                foreach ($element['content'] as $key => $value) {
                    if ($value === '' || $this->isUrlField($key)) continue;
                    $parts[] = strip_tags($value);
                }
            }
        }
        return Str::limit(implode("\n\n", array_values(array_filter(array_map('trim', $parts)))), 50000, '');
    }

    private function isUrlField(string $key): bool
    {
        return $key === 'url' || $key === 'media_url' || $key === 'images' || str_ends_with($key, '_url');
    }
    private function content(string $type, mixed $content): array
    {
        if (! is_array($content)) $this->invalid('Element content must be an object.'); $result = [];
        $definition = $this->elements->definition($type);
        if (array_diff(array_keys($content), $definition['fields'] ?? []) !== []) $this->invalid('The element contains an unsupported content field.');
        foreach ($definition['fields'] ?? [] as $key) {
            $value = $content[$key] ?? '';
            if (! is_scalar($value)) $this->invalid('Element fields must contain plain text.');
            $rules = $definition['field_definitions'][$key]['validation'] ?? [];
            $result[$key] = Str::limit(trim((string) $value), (int) ($rules['max'] ?? 500), '');
            if (isset($rules['enum']) && ! in_array($result[$key], $rules['enum'], true)) $this->invalid('Choose an approved value for '.str_replace('_', ' ', $key).'.');
            if (($rules['format'] ?? null) === 'integer' && (! ctype_digit($result[$key]) || (int) $result[$key] < (int) ($rules['min'] ?? 0) || (int) $result[$key] > (int) ($rules['max_value'] ?? PHP_INT_MAX))) $this->invalid('Enter a valid value for '.str_replace('_', ' ', $key).'.');
        }
        $this->validateContent($type, $result); return $result;
    }
    private function validateContent(string $type, array $content): void
    {
        foreach (['url', 'media_url'] as $field) if (($type !== 'button' || $field !== 'url') && ($content[$field] ?? '') !== '' && ! $this->isSafeMediaUrl($content[$field])) $this->invalid('Media links must use an internal path or an HTTP or HTTPS URL.');
        foreach (['facebook_url', 'instagram_url', 'youtube_url', 'directions_url'] as $field) if (($content[$field] ?? '') !== '' && ! preg_match('#^https?://#i', $content[$field])) $this->invalid('Social and directions links must use an HTTP or HTTPS URL.');
        if (($content['button_url'] ?? '') !== '' && ! preg_match('#^(?:https?://|/)#i', $content['button_url'])) $this->invalid('Button destinations must use an HTTP(S) link or an internal path.');
        if ($type === 'button' && ($content['url'] ?? '') !== '' && ! preg_match('#^(?:https?://|/)#i', $content['url'])) $this->invalid('Button destinations must use an HTTP(S) link or an internal path.');
        if ($type === 'video_embed' && ($content['url'] ?? '') !== '' && ! $this->isSafeMediaUrl($content['url'])) $this->invalid('Video media must use an internal path or an HTTP or HTTPS URL.');
        if ($type === 'gallery') { $images = array_values(array_filter(preg_split('/\R/', $content['images']) ?: [])); if (count($images) > 12 || collect($images)->contains(fn ($url) => ! $this->isSafeMediaUrl(trim($url)))) $this->invalid('A gallery accepts up to 12 internal or HTTP(S) image links.'); if (! in_array($content['columns'], ['two', 'three', 'four'], true)) $this->invalid('Choose a valid gallery column layout.'); if (! in_array($content['display'], ['', 'grid', 'carousel'], true)) $this->invalid('Choose a valid gallery presentation.'); }
        if ($type === 'video_embed' && ! in_array($content['presentation'], ['', 'standard', 'hero'], true)) $this->invalid('Choose a valid video presentation.');
        if ($type === 'media_text' && ! in_array($content['placement'], ['left', 'right'], true)) $this->invalid('Choose a valid media placement.'); if ($type === 'list' && ! in_array($content['ordered'], ['yes', 'no'], true)) $this->invalid('Choose a valid list style.'); if ($type === 'spacer' && ! in_array($content['height'], ['small', 'medium', 'large'], true)) $this->invalid('Choose a valid spacer height.');
        if ($type === 'alert' && ! in_array($content['tone'], ['info', 'success', 'warning'], true)) $this->invalid('Choose a valid alert tone.');
        if ($type === 'progress' && (! ctype_digit($content['value']) || (int) $content['value'] > 100)) $this->invalid('Progress must be a whole number from 0 to 100.');
        if ($type === 'table') { $headers = array_values(array_filter(array_map('trim', explode('|', $content['headers'])))); $rows = array_values(array_filter(preg_split('/\R/', $content['rows']) ?: [])); if (count($headers) < 2 || count($headers) > 6 || count($rows) > 20 || collect($rows)->contains(fn (string $row): bool => count(explode('|', $row)) !== count($headers))) $this->invalid('A table needs two to six headers and up to 20 rows with matching columns.'); }
        if (in_array($type, ['icon', 'icon_box'], true) && ! in_array($content['icon'], ['heart', 'people', 'calendar', 'location', 'message', 'book', 'star', 'check', 'hand-heart', 'globe'], true)) $this->invalid('Choose an approved icon.');
        if (in_array($type, ['html', 'custom_code'], true) && trim($content['reason'] ?? '') === '') $this->invalid('Custom code requires an explanation of why no native element fits.');
        if (in_array($type, ['html', 'custom_code'], true) && trim($content['fallback'] ?? '') === '') $this->invalid('Custom code requires accessible fallback content.');
        if (in_array($type, ['html', 'custom_code'], true)) $this->validateSandboxCode($content['html'] ?? '', $content['css'] ?? '', $content['javascript'] ?? '');
    }

    private function validateSandboxCode(string $html, string $css, string $javascript): void
    {
        if(preg_match('/\b(?:eval|Function|WebAssembly|Worker|SharedWorker|ServiceWorker|fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|localStorage|sessionStorage|indexedDB|cookieStore|setInterval)\b/i',$javascript)) $this->invalid('Custom JavaScript uses a blocked execution or storage API.');
        if(preg_match('/\b(?:window\s*\.\s*)?(?:parent|top|opener)\b/i',$javascript)) $this->invalid('Custom JavaScript cannot access another browsing context.');
        if(preg_match('/\b(?:while|do|for)\s*(?:\(|\{)/i',$javascript)) $this->invalid('Custom JavaScript cannot contain loop statements; use bounded array operations instead.');
        if(preg_match('/\b(?:import|export)\b|\bnew\s+Function\b/i',$javascript)) $this->invalid('Custom JavaScript cannot load modules or construct executable code.');
    }

    private function settings(mixed $settings, int $max, ?array $supportedKeys = null): array
    {
        if (! is_array($settings)) $this->invalid('Page settings must be an object.'); $result = [];
        foreach ($settings as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $key)) $this->invalid('Page settings must contain named scalar values.');
            $key = Str::snake($key);
            if ($supportedKeys !== null && ! in_array($key, $supportedKeys, true)) $this->invalid('The element contains an unsupported design setting.');
            if (in_array($key, ['padding', 'margin'], true) && is_array($value)) {
                $normalisedBox = [];
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $sideValue = $value[$side] ?? '';
                    if (! is_string($sideValue) || ($sideValue !== '' && ! preg_match('/^-?(?:\d+(?:\.\d+)?)(?:px|%|em|rem|vw|vh)$/', trim($sideValue))) || ($key === 'padding' && str_starts_with(trim($sideValue), '-'))) $this->invalid('Spacing values must use px, %, em, rem, vw, or vh units.');
                    $normalisedBox[$side] = trim($sideValue);
                }
                $result[$key] = $normalisedBox;
                continue;
            }
            if (! is_scalar($value)) $this->invalid('Page settings must contain named scalar values.');
            if (in_array($key, ['css', 'style', 'stylesheet', 'html', 'script', 'javascript', 'custom_html'], true)) $this->invalid('Arbitrary markup, scripts, and styles are not allowed in page settings.');
            $value = Str::limit(trim((string) $value), $key === 'custom_css' ? 50000 : $max, '');
            if ($key === 'custom_id' && $value !== '' && ! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/', $value)) $this->invalid('Custom CSS IDs must start with a letter and contain only letters, numbers, hyphens, or underscores.');
            if ($key === 'custom_class' && $value !== '' && ! preg_match('/^[A-Za-z][A-Za-z0-9_-]*(?:\s+[A-Za-z][A-Za-z0-9_-]*)*$/', $value)) $this->invalid('Custom CSS classes must contain only safe class names separated by spaces.');
            if (in_array($key, ['background_color', 'border_color', 'overlay_color', 'text_color', 'button_background', 'button_hover_background', 'button_text_color', 'button_border_color', 'shadow_color'], true) && $value !== '' && ! preg_match('/^(?:#[0-9a-fA-F]{6}|canvas|surface|surface_muted|action|brand)$/', $value)) $this->invalid('Page colours must use an approved theme token or six-digit hex value.');
            $positiveDimensions = ['border_width', 'radius_top_left', 'radius_top_right', 'radius_bottom_right', 'radius_bottom_left', 'shadow_blur', 'font_size', 'button_padding_x', 'button_padding_y', 'button_radius', 'button_border_width', 'content_max_width', 'min_height', 'row_gap', 'element_gap', 'custom_width'];
            $signedDimensions = ['shadow_x', 'shadow_y', 'shadow_spread', 'letter_spacing'];
            if (in_array($key, [...$positiveDimensions, ...$signedDimensions], true) && preg_match('/^-?\d+(?:\.\d+)?$/', $value)) $value .= 'px';
            if (in_array($key, $positiveDimensions, true) && $value !== '' && ! preg_match('/^(?:0|\d+(?:\.\d+)?)(?:px|%|em|rem|vw|vh)$/', $value)) $this->invalid('Style dimensions must use px, %, em, rem, vw, or vh units.');
            if (in_array($key, $signedDimensions, true) && $value !== '' && ! preg_match('/^-?(?:0|\d+(?:\.\d+)?)(?:px|%|em|rem|vw|vh)$/', $value)) $this->invalid('Style dimensions must use px, %, em, rem, vw, or vh units.');
            if ($key === 'line_height' && $value !== '' && ! preg_match('/^(?:\d+(?:\.\d+)?|(?:0|\d+(?:\.\d+)?)(?:px|%|em|rem))$/', $value)) $this->invalid('Line height must be unitless or use px, %, em, or rem.');
            if ($key === 'overlay_opacity' && $value !== '' && (! ctype_digit($value) || (int) $value > 100)) $this->invalid('Overlay opacity must be a whole number from 0 to 100.');
            $allowedSettings = [
                'background' => ['transparent', 'canvas', 'surface', 'surface_muted', 'action', 'brand'],
                'background_overlay' => ['none', 'soft', 'strong'],
                'border' => ['none', 'subtle', 'strong'],
                'radius' => ['none', 'small', 'medium', 'large'],
                'shadow' => ['none', 'small', 'medium'],
                'overflow' => ['visible', 'hidden'],
                'grid_preset' => ['one', 'two', 'three', 'four'],
                'border_style' => ['none', 'solid', 'dashed', 'dotted'],
                'font_family' => ['inherit', 'system', 'verdana', 'trebuchet', 'georgia', 'palatino', 'display'],
                'font_weight' => ['400', '500', '600', '700', '800'],
                'text_transform' => ['none', 'uppercase', 'lowercase', 'capitalize'],
                'button_width' => ['auto', 'full'],
                'content_width' => ['full', 'wide', 'standard', 'narrow', 'custom'],
                'vertical_alignment' => ['start', 'center', 'end', 'stretch'],
                'background_position' => ['left_top', 'center_top', 'right_top', 'left_center', 'center_center', 'right_center', 'left_bottom', 'center_bottom', 'right_bottom'],
                'background_size' => ['auto', 'cover', 'contain'],
                'background_repeat' => ['no_repeat', 'repeat', 'repeat_x', 'repeat_y'],
                'background_attachment' => ['scroll', 'fixed'],
            ];
            if (isset($allowedSettings[$key]) && ! in_array($value, $allowedSettings[$key], true)) $this->invalid('Choose an approved page appearance setting.');
            if ($key === 'background_image' && $value !== '' && ! $this->isSafeMediaUrl($value)) $this->invalid('Choose a valid background image URL or ministry media path.');
            $result[$key] = $value;
        }
        return $result;
    }
    private function responsive(mixed $responsive, ?array $supportedKeys = null): array
    {
        if (! is_array($responsive)) $this->invalid('Responsive settings must be grouped by breakpoint.'); $result = [];
        $allowed = [
            'visibility' => ['visible', 'hidden'],
            'alignment' => ['left', 'center', 'right', 'stretch'],
            'column_count' => ['1', '2', '3', '4'],
            'stack' => ['inline', 'stack'],
            'padding' => ['none', 'compact', 'regular', 'spacious'],
            'margin' => ['none', 'compact', 'regular', 'spacious'],
            'column_gap' => ['none', 'compact', 'regular', 'spacious'],
            'width' => ['full', 'half', 'third', 'custom'],
            'order' => ['1', '2', '3', '4'],
            'gap' => ['none', 'compact', 'regular', 'spacious'],
            'font_size' => null,
            'line_height' => null,
            'letter_spacing' => null,
            'custom_width' => null,
        ];
        foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
            if (! array_key_exists($breakpoint, $responsive)) continue;
            $settings = $this->settings($responsive[$breakpoint], 40);
            foreach ($settings as $key => $value) {
                if (in_array($key, ['padding', 'margin'], true) && is_array($value)) continue;
                if (($supportedKeys !== null && ! in_array($key, $supportedKeys, true)) || ! array_key_exists($key, $allowed) || (is_array($allowed[$key]) && ! in_array($value, $allowed[$key], true))) $this->invalid('Choose a valid responsive setting.');
            }
            $result[$breakpoint] = $settings;
        }
        return $result;
    }
    private function isSafeMediaUrl(string $value): bool
    {
        return preg_match('#^https?://#i', $value) === 1
            || preg_match('#^/(?!/)[A-Za-z0-9._~!$&\'()*+,;=:@%/-]+(?:\?[A-Za-z0-9._~!$&\'()*+,;=:@%/?-]*)?$#', $value) === 1;
    }
    private function id(mixed $id): string { return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id) ? $id : (string) Str::uuid(); }
    private function invalid(string $message): never { throw ValidationException::withMessages(['blocks' => $message]); }
}
