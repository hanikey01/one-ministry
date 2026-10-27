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

    /** @var array<string, array<int, string>> */
    private const ELEMENT_FIELDS = [
        'hero' => ['eyebrow', 'title', 'body', 'button_label', 'button_url'],
        'html' => ['html', 'css', 'javascript'], 'custom_code' => ['html', 'css', 'javascript'],
        'heading' => ['text'], 'paragraph' => ['text'], 'rich_text' => ['title', 'text'], 'button' => ['label', 'url'], 'feature_list' => ['title', 'items'], 'testimonial' => ['quote', 'name', 'role'], 'statistics' => ['title', 'stat_one_value', 'stat_one_label', 'stat_two_value', 'stat_two_label', 'stat_three_value', 'stat_three_label'], 'alert' => ['tone', 'title', 'body'], 'progress' => ['title', 'label', 'value'], 'table' => ['title', 'headers', 'rows'], 'image' => ['url', 'alt', 'caption', 'image_hint'],
        'icon' => ['icon', 'label'], 'icon_box' => ['icon', 'title', 'body', 'button_label', 'button_url'], 'icon_list' => ['title', 'items'], 'image_card' => ['url', 'alt', 'title', 'body', 'button_label', 'button_url'], 'tabs' => ['title', 'tab_one_label', 'tab_one_content', 'tab_two_label', 'tab_two_content', 'tab_three_label', 'tab_three_content'], 'social_links' => ['title', 'facebook_url', 'instagram_url', 'youtube_url'],
        'contact_form' => ['title', 'body', 'submit_label'], 'map_directions' => ['title', 'address', 'directions_url', 'button_label'],
        'gallery' => ['images', 'columns', 'caption'], 'video_embed' => ['url', 'title', 'caption'],
        'audio' => ['url', 'title', 'caption'], 'file' => ['url', 'label', 'description'],
        'media_text' => ['media_url', 'media_alt', 'placement', 'title', 'body', 'button_label', 'button_url'],
        'list' => ['title', 'items', 'ordered'],
        'accordion' => ['title', 'question_one', 'answer_one', 'question_two', 'answer_two', 'question_three', 'answer_three'],
        'spacer' => ['height'], 'call_to_action' => ['title', 'body', 'button_label', 'button_url'],
        'quote' => ['text', 'attribution'], 'service_times' => ['title', 'body'], 'events' => ['title', 'body'],
        'groups' => ['title', 'body'], 'locations' => ['title', 'body', 'button_label', 'button_url'],
        'carousel' => ['eyebrow', 'title', 'body', 'slides', 'button_label', 'button_url'],
        'live_service' => ['title', 'body', 'button_label', 'button_url'],
        'giving' => ['title', 'body', 'button_label', 'button_url'], 'prayer' => ['title', 'body', 'button_label', 'button_url'],
        'sermon_spotlight' => ['title', 'body', 'button_label', 'button_url'], 'books' => ['title', 'body', 'button_label', 'button_url'],
        'announcements' => ['title', 'body'], 'volunteer' => ['title', 'body'], 'newsletter' => ['title', 'body'],
        'next_steps' => ['title', 'body'], 'welcome' => ['title', 'body'], 'custom' => ['title', 'body', 'button_label', 'button_url'], 'divider' => [],
    ];
    private const FEATURE_ELEMENTS = ['groups' => 'groups', 'live_service' => 'digital_church', 'giving' => 'giving', 'books' => 'books_library', 'volunteer' => 'volunteers'];

    /** @param array<int, string> $enabledFeatures
     * @return array{version: 2, sections: array<int, array<string, mixed>>} */
    public function normalise(array $document, array $enabledFeatures = []): array
    {
        if (($document['version'] ?? null) !== self::VERSION || ! is_array($document['sections'] ?? null)) $this->invalid('The page document version is not supported.');
        if (count($document['sections']) > self::MAX_SECTIONS) $this->invalid('A page can contain up to 60 sections.');
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
                    if (! array_key_exists($type, self::ELEMENT_FIELDS)) $this->invalid('The page contains an unsupported element.');
                    if (isset(self::FEATURE_ELEMENTS[$type]) && ! in_array(self::FEATURE_ELEMENTS[$type], $enabledFeatures, true)) $this->invalid('The page contains an element from a disabled module.');
                    $normalisedElements[] = ['id' => $this->id($element['id'] ?? null), 'type' => $type, 'content' => $this->content($type, $element['content'] ?? []), 'settings' => $this->settings($element['settings'] ?? [], 200), 'responsive' => $this->responsive($element['responsive'] ?? [])];
                }
                $normalisedColumns[] = ['id' => $this->id($column['id'] ?? null), 'settings' => $this->settings($column['settings'] ?? [], 200), 'responsive' => $this->responsive($column['responsive'] ?? []), 'elements' => $normalisedElements];
            }
            $sections[] = ['id' => $this->id($section['id'] ?? null), 'type' => 'section', 'settings' => $this->settings($section['settings'] ?? [], 200), 'responsive' => $this->responsive($section['responsive'] ?? []), 'columns' => $normalisedColumns];
        }
        return ['version' => self::VERSION, 'sections' => $sections];
    }

    /** Convert a legacy flat block list once, without inventing copy. */
    public function fromFlat(array $blocks, array $enabledFeatures = []): array
    {
        $sections = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) $this->invalid('The page contains an unsupported block.');
            $type = Str::lower(trim($block['type'])); $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $sections[] = $type === 'section' ? $this->legacySection($data) : ($type === 'media_text' ? $this->mediaTextSection($data) : $this->sectionWithElement($type, $data));
        }
        return $this->normalise(['version' => self::VERSION, 'sections' => $sections], $enabledFeatures);
    }

    public function isStructured(mixed $document): bool { return is_array($document) && ($document['version'] ?? null) === self::VERSION && is_array($document['sections'] ?? null); }

    /** @return array<int, array{type: string, data: array<string, string>}> */
    public function flatten(array $document, array $enabledFeatures = []): array
    {
        $flat = [];
        foreach ($this->normalise($document, $enabledFeatures)['sections'] as $section) foreach ($section['columns'] as $column) foreach ($column['elements'] as $element) $flat[] = ['type' => $element['type'], 'data' => array_merge($element['content'], $element['settings'])];
        return $flat;
    }

    private function sectionWithElement(string $type, array $data): array
    {
        return ['id' => (string) Str::uuid(), 'type' => 'section', 'settings' => [], 'responsive' => [], 'columns' => [
            ['id' => (string) Str::uuid(), 'settings' => [], 'responsive' => [], 'elements' => [
                ['id' => (string) Str::uuid(), 'type' => $type, 'content' => $data, 'settings' => [], 'responsive' => []],
            ]],
        ]];
    }
    private function legacySection(array $data): array
    {
        $layout = $data['layout'] ?? 'two'; $count = ['one' => 1, 'two' => 2, 'three' => 3][$layout] ?? 2; $columns = [];
        foreach (range(1, $count) as $position) { $word = ['', 'one', 'two', 'three'][$position]; $columns[] = ['id' => (string) Str::uuid(), 'settings' => [], 'responsive' => [], 'elements' => [['id' => (string) Str::uuid(), 'type' => 'paragraph', 'content' => ['text' => trim((string) (($data['column_'.$word.'_title'] ?? '')."\n".($data['column_'.$word.'_body'] ?? '')))], 'settings' => [], 'responsive' => []]]]; }
        return ['id' => (string) Str::uuid(), 'type' => 'section', 'settings' => array_filter(['background_color' => $data['background_color'] ?? '', 'padding' => $data['padding'] ?? 'regular', 'title' => $data['title'] ?? '', 'body' => $data['body'] ?? ''], fn ($value) => $value !== ''), 'responsive' => [], 'columns' => $columns];
    }
    private function mediaTextSection(array $data): array
    {
        return ['id' => (string) Str::uuid(), 'type' => 'section', 'settings' => [], 'responsive' => [], 'columns' => [
            ['id' => (string) Str::uuid(), 'settings' => [], 'responsive' => [], 'elements' => [['id' => (string) Str::uuid(), 'type' => 'image', 'content' => ['url' => $data['media_url'] ?? '', 'alt' => $data['media_alt'] ?? ''], 'settings' => [], 'responsive' => []]]],
            ['id' => (string) Str::uuid(), 'settings' => [], 'responsive' => [], 'elements' => [['id' => (string) Str::uuid(), 'type' => 'call_to_action', 'content' => ['title' => $data['title'] ?? '', 'body' => $data['body'] ?? '', 'button_label' => $data['button_label'] ?? '', 'button_url' => $data['button_url'] ?? ''], 'settings' => [], 'responsive' => []]]],
        ]];
    }
    private function content(string $type, mixed $content): array
    {
        if (! is_array($content)) $this->invalid('Element content must be an object.'); $result = [];
        foreach (self::ELEMENT_FIELDS[$type] as $key) { $value = $content[$key] ?? ''; if (! is_scalar($value)) $this->invalid('Element fields must contain plain text.'); $limit = $type === 'custom_code' ? 50000 : (in_array($key, ['body', 'text', 'items', 'images'], true) ? 12000 : 500); $result[$key] = Str::limit(trim((string) $value), $limit, ''); }
        $this->validateContent($type, $result); return $result;
    }
    private function validateContent(string $type, array $content): void
    {
        foreach (['url', 'media_url', 'facebook_url', 'instagram_url', 'youtube_url', 'directions_url'] as $field) if (($type !== 'button' || $field !== 'url') && ($content[$field] ?? '') !== '' && ! preg_match('#^https?://#i', $content[$field])) $this->invalid('Media, social, and directions links must use an HTTP or HTTPS URL.');
        if (($content['button_url'] ?? '') !== '' && ! preg_match('#^(?:https?://|/)#i', $content['button_url'])) $this->invalid('Button destinations must use an HTTP(S) link or an internal path.');
        if ($type === 'button' && ($content['url'] ?? '') !== '' && ! preg_match('#^(?:https?://|/)#i', $content['url'])) $this->invalid('Button destinations must use an HTTP(S) link or an internal path.');
        if ($type === 'video_embed' && ($content['url'] ?? '') !== '' && ! $this->isSupportedVideoUrl($content['url'])) $this->invalid('Video embeds support YouTube, Vimeo, or a published ministry video.');
        if ($type === 'gallery') { $images = array_values(array_filter(preg_split('/\R/', $content['images']) ?: [])); if (count($images) > 12 || collect($images)->contains(fn ($url) => ! preg_match('#^https?://#i', trim($url)))) $this->invalid('A gallery accepts up to 12 HTTP or HTTPS image links.'); if (! in_array($content['columns'], ['two', 'three', 'four'], true)) $this->invalid('Choose a valid gallery column layout.'); }
        if ($type === 'media_text' && ! in_array($content['placement'], ['left', 'right'], true)) $this->invalid('Choose a valid media placement.'); if ($type === 'list' && ! in_array($content['ordered'], ['yes', 'no'], true)) $this->invalid('Choose a valid list style.'); if ($type === 'spacer' && ! in_array($content['height'], ['small', 'medium', 'large'], true)) $this->invalid('Choose a valid spacer height.');
        if ($type === 'alert' && ! in_array($content['tone'], ['info', 'success', 'warning'], true)) $this->invalid('Choose a valid alert tone.');
        if ($type === 'progress' && (! ctype_digit($content['value']) || (int) $content['value'] > 100)) $this->invalid('Progress must be a whole number from 0 to 100.');
        if ($type === 'table') { $headers = array_values(array_filter(array_map('trim', explode('|', $content['headers'])))); $rows = array_values(array_filter(preg_split('/\R/', $content['rows']) ?: [])); if (count($headers) < 2 || count($headers) > 6 || count($rows) > 20 || collect($rows)->contains(fn (string $row): bool => count(explode('|', $row)) !== count($headers))) $this->invalid('A table needs two to six headers and up to 20 rows with matching columns.'); }
        if (in_array($type, ['icon', 'icon_box'], true) && ! in_array($content['icon'], ['heart', 'people', 'calendar', 'location', 'message', 'book', 'star', 'check', 'hand-heart', 'globe'], true)) $this->invalid('Choose an approved icon.');
    }

    private function isSupportedVideoUrl(string $url): bool
    {
        return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/)|vimeo\.com/(?:video/)?)[A-Za-z0-9_-]+~i', $url) === 1
            || preg_match('~/media/[0-9a-f-]{36}(?:\?.*)?$~i', $url) === 1;
    }
    private function settings(mixed $settings, int $max): array
    {
        if (! is_array($settings)) $this->invalid('Page settings must be an object.'); $result = [];
        foreach ($settings as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $key)) $this->invalid('Page settings must contain named scalar values.');
            $key = Str::snake($key);
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
            $value = Str::limit(trim((string) $value), in_array($key, ['custom_css', 'custom_js'], true) ? 50000 : $max, '');
            if ($key === 'custom_id' && $value !== '' && ! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/', $value)) $this->invalid('Custom CSS IDs must start with a letter and contain only letters, numbers, hyphens, or underscores.');
            if ($key === 'custom_class' && $value !== '' && ! preg_match('/^[A-Za-z][A-Za-z0-9_-]*(?:\s+[A-Za-z][A-Za-z0-9_-]*)*$/', $value)) $this->invalid('Custom CSS classes must contain only safe class names separated by spaces.');
            if (in_array($key, ['background_color', 'border_color', 'overlay_color'], true) && $value !== '' && ! preg_match('/^(?:#[0-9a-fA-F]{6}|canvas|surface|surface_muted|action|brand)$/', $value)) $this->invalid('Page colours must use an approved theme token or six-digit hex value.');
            if (in_array($key, ['border_width', 'radius_top_left', 'radius_top_right', 'radius_bottom_right', 'radius_bottom_left', 'shadow_x', 'shadow_y', 'shadow_blur', 'shadow_spread'], true) && $value !== '' && ! preg_match('/^(?:0|\d+(?:\.\d+)?)(?:px|%|em|rem|vw|vh)$/', $value)) $this->invalid('Style dimensions must use px, %, em, rem, vw, or vh units.');
            $allowedSettings = [
                'background' => ['transparent', 'canvas', 'surface', 'surface_muted', 'action', 'brand'],
                'background_overlay' => ['none', 'soft', 'strong'],
                'border' => ['none', 'subtle', 'strong'],
                'radius' => ['none', 'small', 'medium', 'large'],
                'shadow' => ['none', 'small', 'medium'],
                'overflow' => ['visible', 'hidden'],
                'grid_preset' => ['one', 'two', 'three', 'four'],
                'border_style' => ['none', 'solid', 'dashed', 'dotted'],
            ];
            if (isset($allowedSettings[$key]) && ! in_array($value, $allowedSettings[$key], true)) $this->invalid('Choose an approved page appearance setting.');
            if ($key === 'background_image' && $value !== '' && ! filter_var($value, FILTER_VALIDATE_URL)) $this->invalid('Choose a valid background image URL.');
            $result[$key] = $value;
        }
        return $result;
    }
    private function responsive(mixed $responsive): array
    {
        if (! is_array($responsive)) $this->invalid('Responsive settings must be grouped by breakpoint.'); $result = [];
        $allowed = [
            'visibility' => ['visible', 'hidden'],
            'alignment' => ['left', 'center', 'right', 'stretch'],
            'column_count' => ['1', '2', '3', '4'],
            'stack' => ['inline', 'stack'],
            'padding' => ['compact', 'regular', 'spacious'],
            'margin' => ['none', 'compact', 'regular', 'spacious'],
            'column_gap' => ['compact', 'regular', 'spacious'],
            'width' => ['full', 'half', 'third'],
            'order' => ['1', '2', '3', '4'],
            'gap' => ['compact', 'regular', 'spacious'],
        ];
        foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
            if (! array_key_exists($breakpoint, $responsive)) continue;
            $settings = $this->settings($responsive[$breakpoint], 40);
            foreach ($settings as $key => $value) {
                if (! array_key_exists($key, $allowed) || ! in_array($value, $allowed[$key], true)) $this->invalid('Choose a valid responsive setting.');
            }
            $result[$breakpoint] = $settings;
        }
        return $result;
    }
    private function id(mixed $id): string { return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id) ? $id : (string) Str::uuid(); }
    private function invalid(string $message): never { throw ValidationException::withMessages(['blocks' => $message]); }
}
