<?php

namespace App\Services\Ai;

use App\Services\PageDocumentService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AiCanvasSectionService
{
    public const MAX_SECTIONS = 12;

    public const MAX_COMPOSITION_BYTES = 650000;

    public static function schema(): array
    {
        $text = fn (string $description): array => ['type' => 'string', 'description' => $description];

        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'section' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'name' => $text('Short internal name for this section.'),
                'purpose' => $text('The section role in the page narrative and visitor journey.'),
                'html' => $text('Complete semantic section HTML with editable text and media markers.'),
                'css' => $text('Complete scoped visual design, layout, responsive behavior and reduced-motion CSS.'),
                'javascript' => $text('Must be an empty string.'),
                'fallback' => $text('Useful plain-text version of the section content.'),
            ], 'required' => ['name', 'purpose', 'html', 'css', 'javascript', 'fallback']],
        ], 'required' => ['section']];
    }

    public static function batchSchema(): array
    {
        $section = self::schema()['properties']['section'];

        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'sections' => ['type' => 'array', 'items' => $section],
        ], 'required' => ['sections']];
    }

    public function normaliseSection(mixed $section, array $approvedMediaPaths = []): array
    {
        if (! is_array($section) || array_diff(array_keys($section), ['name', 'purpose', 'html', 'css', 'javascript', 'fallback']) !== []) {
            $this->invalid('invalid_canvas_section');
        }
        $result = [];
        foreach (['name' => 100, 'purpose' => 1000, 'html' => 50000, 'css' => 50000, 'javascript' => 50000, 'fallback' => 2000] as $field => $limit) {
            if (! is_string($section[$field] ?? null)) $this->invalid('invalid_canvas_section');
            $result[$field] = trim(Str::limit($section[$field], $limit, ''));
            if (in_array($field, ['html', 'css'], true)) $result[$field] = $this->unwrapCodeFence($result[$field]);
        }
        if ($result['name'] === '' || $result['purpose'] === '' || $result['html'] === '' || $result['css'] === '' || $result['fallback'] === '') {
            $this->invalid('incomplete_canvas_section');
        }
        if ($result['javascript'] !== '') {
            $this->invalid('canvas_javascript_not_enabled');
        }
        if (! preg_match('/^<section\b[\s\S]*<\/section>$/i', $result['html'])
            || preg_match('/<(?:script|iframe|object|embed|base|link|meta)\b|\son[a-z]+\s*=|javascript\s*:/i', $result['html'])) {
            $this->invalid('unsafe_canvas_html');
        }
        if (preg_match('/<(?:form|button|input)\b[^>]*\b(?:action|formaction)\s*=/i', $result['html'])) {
            $this->invalid('unsafe_canvas_html');
        }
        if (preg_match('/(?:@import|expression\s*\(|behavior\s*:|javascript\s*:|url\s*\(\s*["\']?\s*(?:https?:)?\/\/)/i', $result['html'])) {
            $this->invalid('unsafe_canvas_css');
        }
        if (preg_match('/(?:@import|expression\s*\(|behavior\s*:|javascript\s*:|https?:\/\/)/i', $result['css'])) {
            $this->invalid('unsafe_canvas_css');
        }
        $hasResponsiveRule = preg_match('/@media\s*\([^)]*(?:(?:max|min)-width\s*:|width\s*(?:[<>]=?|:))[^)]*\)/i', $result['css'])
            || preg_match('/@container\b[^\{]*\([^)]*(?:width|inline-size)[^)]*\)/i', $result['css']);
        if (! preg_match('/[^{}]+\{[^{}]+\}/s', $result['css']) || ! $hasResponsiveRule) {
            $this->invalid('insufficient_canvas_css');
        }
        $editableKeys = $this->markerKeys($result['html'], 'data-ai-editable');
        if ($editableKeys === [] || count($editableKeys) > 40 || count($editableKeys) !== count(array_unique($editableKeys))) {
            $this->invalid('invalid_canvas_editable_markers');
        }
        $mediaKeys = $this->markerKeys($result['html'], 'data-ai-media');
        if (count($mediaKeys) > 12 || count($mediaKeys) !== count(array_unique($mediaKeys))) {
            $this->invalid('invalid_canvas_media_markers');
        }
        preg_match_all('/\b(?:src|poster)\s*=\s*(["\'])(.*?)\1/is', $result['html'], $mediaSources);
        foreach ($mediaSources[2] ?? [] as $source) {
            $path = trim($source);
            if ($path !== '' && (! str_starts_with($path, '/') || ! in_array($path, $approvedMediaPaths, true))) {
                $this->invalid('canvas_media_must_use_library');
            }
        }
        if (preg_match('/\bsrcset\s*=/i', $result['html'])) $this->invalid('canvas_media_must_use_library');
        // AI calls to action are visual placeholders until staff deliberately
        // assigns a destination. A fragment or empty href still resolves
        // against the host URL inside srcdoc and can load the page recursively
        // inside its own sandbox, so remove navigation rather than substituting
        // another URL.
        $result['html'] = self::neutraliseLinks($result['html']);
        preg_match_all('/<[^>]+\bdata-ai-media\s*=\s*(["\']).*?\1[^>]*>/is', $result['html'], $mediaTags);
        foreach ($mediaTags[0] ?? [] as $tag) {
            if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $source) && trim($source[2]) !== '') {
                $path = trim($source[2]);
                if (! str_starts_with($path, '/') || ! in_array($path, $approvedMediaPaths, true)) $this->invalid('canvas_media_must_use_library');
            }
        }
        if (preg_match_all('/\bdata-ai-media-type\s*=\s*(["\'])(.*?)\1/is', $result['html'], $types)) {
            foreach ($types[2] as $type) if (! in_array(strtolower(trim($type)), ['image', 'video', 'audio'], true)) {
                $this->invalid('invalid_canvas_media_type');
            }
        }

        return $result;
    }

    public static function neutraliseLinks(string $html): string
    {
        return preg_replace_callback('/<a\b[^>]*>/is', static function (array $match): string {
            $tag = preg_replace('/\s+href\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[0]) ?? $match[0];
            $tag = preg_replace('/\s+target\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag) ?? $tag;
            if (! preg_match('/\saria-disabled\s*=/i', $tag)) {
                $tag = preg_replace('/>$/', ' aria-disabled="true">', $tag) ?? $tag;
            }
            if (! preg_match('/\sdata-ai-link-placeholder(?:\s|=|>)/i', $tag)) {
                $tag = preg_replace('/>$/', ' data-ai-link-placeholder="">', $tag) ?? $tag;
            }

            return $tag;
        }, $html) ?? $html;
    }

    public function compile(array $composition, array $features, array $approvedMediaPaths = []): array
    {
        if (strlen(json_encode($composition, JSON_THROW_ON_ERROR)) > self::MAX_COMPOSITION_BYTES) {
            $this->invalid('canvas_composition_too_large');
        }
        $sections = $composition['sections'] ?? null;
        if (! is_array($sections) || ! array_is_list($sections) || $sections === [] || count($sections) > self::MAX_SECTIONS) {
            $this->invalid('invalid_canvas_size');
        }
        $document = ['version' => PageDocumentService::VERSION, 'sections' => []];
        foreach ($sections as $position => $candidate) {
            $section = $this->normaliseSection($candidate, $approvedMediaPaths);
            $id = 'ai-section-'.($position + 1).'-'.Str::lower(Str::random(8));
            $document['sections'][] = [
                'id' => $id, 'type' => 'section',
                'settings' => ['title' => $section['name'], 'content_width' => 'full', 'padding' => ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px'], 'custom_class' => 'ai-canvas-section'],
                'responsive' => [],
                'columns' => [[
                    'id' => $id.'-column', 'settings' => ['element_gap' => '0px'], 'responsive' => ['desktop' => ['width' => 'full'], 'mobile' => ['width' => 'full']],
                    'elements' => [[
                        'id' => $id.'-design', 'type' => 'custom_code',
                        'content' => ['reason' => $section['purpose'], 'html' => $section['html'], 'css' => $section['css'], 'javascript' => '', 'fallback' => $section['fallback']],
                        'settings' => ['custom_class' => 'ai-canvas-design'], 'responsive' => [],
                    ]],
                ]],
            ];
        }

        $title = trim(strip_tags((string) ($composition['title'] ?? '')));
        if ($title === '') $this->invalid('invalid_page_metadata');

        return [
            'title' => Str::limit($title, 200, ''),
            'seo_title' => Str::limit(trim(strip_tags((string) ($composition['seo_title'] ?? $title))), 70, ''),
            'seo_description' => Str::limit(trim(strip_tags((string) ($composition['seo_description'] ?? ''))), 160, ''),
            'document' => app(PageDocumentService::class)->normalise($document, $features),
        ];
    }

    private function invalid(string $code): never
    {
        throw ValidationException::withMessages(['composition' => $code]);
    }

    /** @return array<int,string> */
    private function markerKeys(string $html, string $attribute): array
    {
        preg_match_all('/\b'.preg_quote($attribute, '/').'\s*=\s*(["\'])(.*?)\1/is', $html, $matches);
        $keys = [];
        foreach ($matches[2] ?? [] as $key) {
            $key = trim($key);
            if (! preg_match('/^[a-z][a-z0-9-]{0,59}$/', $key)) $this->invalid('invalid_canvas_marker_key');
            $keys[] = $key;
        }
        return $keys;
    }

    private function unwrapCodeFence(string $value): string
    {
        if (preg_match('/^```(?:html|css)?\s*\R?([\s\S]*?)\R?```$/i', $value, $match)) {
            return trim($match[1]);
        }

        return $value;
    }
}
