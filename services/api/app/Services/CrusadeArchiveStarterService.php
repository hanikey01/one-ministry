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
                $this->column([$this->element('hero', [
                    'eyebrow' => 'CRUSADE ARCHIVE',
                    'title' => 'A record of faith, service, and gathering.',
                    'body' => 'Add the verified crusade theme, dates, location, and a short introduction to preserve this moment in the ministry journey.',
                    'button_label' => '', 'button_url' => '',
                ], [
                    'text_color' => '#ffffff',
                    'custom_css' => 'selector .public-page-hero { min-height: clamp(460px, 58vw, 620px); align-content: end; justify-items: start; padding: clamp(42px, 7vw, 84px); background: linear-gradient(135deg, var(--brand), color-mix(in srgb, var(--action) 64%, var(--brand))); } selector small { width: max-content; border: 1px solid rgb(255 255 255 / 38%); border-radius: 999px; padding: 7px 12px; font-weight: 800; letter-spacing: .16em; } selector h1 { max-width: 12ch; font-family: var(--font-heading, Georgia, serif); font-size: clamp(2.8rem, 5.4vw, 4.8rem); line-height: .98; letter-spacing: -.055em; } selector p { max-width: 54ch; font-size: 1.05rem; line-height: 1.7; } @media (max-width: 620px) { selector .public-page-hero { min-height: 460px; padding: 36px 24px 48px; } selector h1 { font-size: clamp(2.35rem, 9vw, 3.1rem); } selector p { font-size: .98rem; line-height: 1.6; } }',
                ])]),
            ], ['content_width' => 'wide', 'background' => 'canvas']),

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
                    'caption' => 'Add selected crusade photographs from the Media Library or use approved image URLs.',
                ], [
                    'custom_css' => 'selector .public-page-gallery { gap: 18px; } selector .public-page-gallery img { aspect-ratio: 4 / 3; border-radius: 18px; }',
                ])]),
            ], ['title' => 'Crusade in images', 'body' => 'A visual record of the people, prayer, service, and gathering throughout the crusade.', 'content_width' => 'wide', 'background' => 'surface_muted', 'custom_css' => 'selector .public-page-structured-heading { margin-bottom: 30px; }']),

            $this->section([
                $this->column([$this->video('Highlight one', 'Add a short uploaded highlight or a supported YouTube/Vimeo link.')]),
                $this->column([$this->video('Highlight two', 'Add another short moment from the crusade journey.')]),
                $this->column([$this->video('Highlight three', 'Add a final short highlight, testimony, or worship moment.')]),
            ], ['title' => 'Short video highlights', 'body' => 'Each video accepts a ministry upload from the Media Library or a supported YouTube/Vimeo link.', 'content_width' => 'wide', 'grid_preset' => 'three', 'column_gap' => 'regular', 'background' => 'canvas'], ['tablet' => ['column_count' => '2'], 'mobile' => ['stack' => 'stack']]),

            $this->section([
                $this->column([$this->element('video_embed', [
                    'url' => '',
                    'title' => 'Full crusade recording',
                    'caption' => 'Add the complete recording as a ministry video upload or a supported YouTube/Vimeo link.',
                ], [
                    'custom_css' => 'selector .public-page-video { max-width: 1040px; margin: 0 auto; } selector .public-page-video :is(video, iframe) { border-radius: 22px; aspect-ratio: 16 / 9; background: var(--ink); } selector figcaption { margin-top: 16px; color: var(--muted); text-align: center; }',
                ])]),
            ], ['title' => 'Watch the full crusade', 'body' => 'Preserve the complete service or event recording here for the ministry archive.', 'content_width' => 'wide', 'background' => 'surface_muted', 'custom_css' => 'selector .public-page-structured-heading { margin-bottom: 30px; }']),
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
