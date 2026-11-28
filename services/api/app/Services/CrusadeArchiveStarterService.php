<?php

namespace App\Services;

use Illuminate\Support\Str;

final class CrusadeArchiveStarterService
{
    public function __construct(private readonly PageDocumentService $documents) {}

    /** @param array<int, string> $features */
    public function document(array $features): array
    {
        $sections = [
            $this->section([
                $this->column([$this->element('video_embed', [
                    'url' => '',
                    'title' => 'Full crusade recording',
                    'caption' => 'Add the complete recording as a ministry video upload or a supported external video link.',
                    'presentation' => 'standard',
                ], [
                    'custom_css' => 'selector, selector .public-page-video, selector :is(video, iframe) { display: block; box-sizing: border-box; width: 100% !important; max-width: none !important; margin: 0; border: 0; border-radius: 0; } selector .public-page-video { background: var(--ink); } selector .public-page-video :is(video, iframe) { aspect-ratio: 16 / 9; object-fit: contain; } selector figcaption { margin: 0; padding: 14px 20px; background: var(--surface); color: var(--muted); text-align: center; }',
                ])]),
            ], ['content_width' => 'full', 'column_gap' => 'none', 'background' => 'canvas', 'custom_class' => 'crusade-video-hero-section'], ['desktop' => ['padding' => $this->box('0px', '0px')], 'tablet' => ['padding' => $this->box('0px', '0px')], 'mobile' => ['padding' => $this->box('0px', '0px')]]),

            $this->section([
                $this->column([$this->element('rich_text', [
                    'title' => 'The story of the crusade.',
                    'text' => 'Use this space for a factual record of the crusade: its purpose, the community it served, and the ministry moments that should be remembered. Add only verified names, dates, places, and outcomes.',
                ], $this->headingStyle())]),
            ], ['content_width' => 'standard', 'background' => 'canvas']),

            $this->section([
                $this->column([$this->element('gallery', [
                    'images' => '',
                    'columns' => 'three',
                    'display' => 'carousel',
                    'caption' => 'Add selected crusade photographs from the Media Library or use approved image URLs.',
                ], [
                    'custom_css' => 'selector .public-page-gallery-carousel { width: 100%; max-width: 100%; margin-inline: auto; } selector .public-page-gallery-carousel-stage { width: 100%; max-width: 100%; min-width: 0; min-height: 0; aspect-ratio: 16 / 9; border-radius: 20px; } selector .public-page-gallery-carousel-stage img { object-fit: cover; } @media (max-width: 760px) { selector .public-page-gallery-carousel-stage { aspect-ratio: 4 / 3; } }',
                ])]),
            ], ['title' => 'Crusade in images', 'body' => 'A visual record of the people, prayer, service, and gathering throughout the crusade.', 'content_width' => 'wide', 'background' => 'surface_muted', 'custom_css' => 'selector .public-page-structured-heading { margin-bottom: 30px; }']),

            $this->section([
                $this->column([$this->video('Highlight one', 'Add a short uploaded highlight or a supported YouTube/Vimeo link.')]),
                $this->column([$this->video('Highlight two', 'Add another short moment from the crusade journey.')]),
                $this->column([$this->video('Highlight three', 'Add a final short highlight, testimony, or worship moment.')]),
            ], ['title' => 'Short video highlights', 'body' => 'Each video accepts a ministry upload from the Media Library or a supported YouTube/Vimeo link.', 'content_width' => 'wide', 'grid_preset' => 'three', 'column_gap' => 'regular', 'background' => 'canvas'], ['tablet' => ['column_count' => '2'], 'mobile' => ['stack' => 'stack']]),

        ];

        return $this->documents->normalise(['version' => 2, 'sections' => $sections], $features);
    }

    private function video(string $title, string $caption): array
    {
        return $this->element('video_embed', ['url' => '', 'title' => $title, 'caption' => $caption], [
            'custom_css' => 'selector .public-page-video { height: 100%; margin: 0; } selector .public-page-video iframe, selector .public-page-video video { aspect-ratio: 16 / 9; border-radius: 18px; background: var(--ink); } selector figcaption { margin-top: 12px; color: var(--muted); font-size: .9rem; line-height: 1.55; }',
        ]);
    }

    private function headingStyle(): array
    {
        return ['custom_css' => 'selector { max-width: 800px; } selector h2 { color: var(--ink) !important; font-family: var(--font-heading, Georgia, serif); font-size: clamp(2.25rem, 4.5vw, 4.25rem); line-height: 1; letter-spacing: -.055em; } selector p { color: color-mix(in srgb, var(--ink) 74%, var(--canvas)) !important; font-size: 1.04rem; line-height: 1.85; }'];
    }

    /** @param array<int, array<string, mixed>> $columns */
    private function section(array $columns, array $settings = [], array $responsive = []): array
    {
        $settings += ['content_width' => 'wide', 'column_gap' => 'spacious'];
        $responsive['desktop'] = ($responsive['desktop'] ?? []) + ['padding' => $this->box('88px', '32px')];
        $responsive['tablet'] = ($responsive['tablet'] ?? []) + ['padding' => $this->box('64px', '24px')];
        $responsive['mobile'] = ($responsive['mobile'] ?? []) + ['padding' => $this->box('46px', '20px')];

        return ['id' => (string) Str::uuid(), 'type' => 'section', 'settings' => $settings, 'responsive' => $responsive, 'columns' => $columns];
    }

    /** @param array<int, array<string, mixed>> $elements */
    private function column(array $elements): array
    {
        return ['id' => (string) Str::uuid(), 'settings' => [], 'responsive' => [], 'elements' => $elements];
    }

    private function element(string $type, array $content, array $settings = []): array
    {
        return ['id' => (string) Str::uuid(), 'type' => $type, 'content' => $content, 'settings' => $settings, 'responsive' => []];
    }

    private function box(string $vertical, string $horizontal): array
    {
        return ['top' => $vertical, 'right' => $horizontal, 'bottom' => $vertical, 'left' => $horizontal];
    }
}
