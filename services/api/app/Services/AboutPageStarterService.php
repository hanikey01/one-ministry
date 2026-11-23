<?php

namespace App\Services;

use Illuminate\Support\Str;

final class AboutPageStarterService
{
    public function __construct(private readonly PageDocumentService $documents) {}

    /** @param array<int, string> $features */
    public function document(array $features): array
    {
        $sections = [
            $this->section([
                $this->column([$this->element('hero', [
                    'eyebrow' => 'ABOUT US',
                    'title' => 'A community shaped by faith, hope, and love.',
                    'body' => 'Learn the heart behind the ministry, the values that guide its work, and the people it is called to serve.',
                    'button_label' => 'View service times',
                    'button_url' => '/service-times',
                ], [
                    'text_color' => '#ffffff',
                    'button_background' => 'surface',
                    'button_hover_background' => 'surface_muted',
                    'button_text_color' => 'brand',
                    'button_radius' => '999px',
                    'button_padding_x' => '25px',
                    'button_padding_y' => '14px',
                    'button_width' => 'auto',
                    'custom_css' => 'selector { width: 100% !important; max-width: none !important; } selector .public-page-hero { width: min(100%, 590px) !important; min-height: clamp(500px, 60vw, 640px); margin: 0 auto 0 0 !important; justify-self: start !important; align-content: end; justify-items: start; text-align: left; padding: 42px clamp(34px, 5vw, 68px) 52px; background: transparent; } selector small { width: max-content; border: 1px solid color-mix(in srgb, currentColor 34%, transparent); border-radius: 999px; padding: 7px 12px; font-weight: 800; letter-spacing: .17em; } selector h1 { max-width: 11ch; font-family: var(--font-heading, Georgia, serif); font-size: clamp(2.45rem, 3.7vw, 3.7rem); line-height: .98; letter-spacing: -.055em; } selector p { max-width: 40ch; font-size: 1rem; line-height: 1.65; } selector .button { width: fit-content !important; min-width: 0; } @media (max-width: 980px) { selector .public-page-hero { width: min(100%, 560px) !important; min-height: 530px; padding: 38px 42px 48px; } selector h1 { max-width: 11ch; font-size: clamp(2.35rem, 5.6vw, 3.4rem); } } @media (max-width: 620px) { selector .public-page-hero { width: 100% !important; min-height: 500px; padding: 34px 26px 46px; } selector h1 { max-width: 10ch; font-size: clamp(2.25rem, 8.2vw, 2.9rem); line-height: 1; } selector p { max-width: 34ch; font-size: .98rem; line-height: 1.6; } selector small { font-size: .68rem; } }',
                ])]),
            ], [
                'content_width' => 'wide', 'min_height' => '680px', 'vertical_alignment' => 'end',
                'background_image' => '/images/pages/home-arrival-hero.png', 'background_overlay' => 'strong',
                'overlay_color' => 'brand', 'overlay_opacity' => '72', 'background_position' => 'center_center',
                'background_size' => 'cover', 'background_repeat' => 'no_repeat', 'overflow' => 'hidden',
                'custom_class' => 'proposed-about-hero',
            ]),

            $this->section([
                $this->column([$this->element('rich_text', [
                    'title' => 'A story still being written.',
                    'text' => 'Replace this introduction with the ministry’s verified origin story: how the community began, the people and place it is called to serve, and the ways its mission has grown over time. Keep the story honest, specific, and centred on people rather than claims that cannot be verified.',
                ], $this->headingStyle())]),
            ], ['content_width' => 'standard', 'background' => 'canvas']),

            $this->section([
                $this->column([$this->milestone('book', 'Rooted', 'Share the convictions, prayer, or calling that formed the ministry’s foundation.')]),
                $this->column([$this->milestone('people', 'Growing', 'Describe how the ministry learned to welcome, disciple, and care for its community.')]),
                $this->column([$this->milestone('globe', 'Sent', 'Explain how the ministry now serves its neighbours and participates in a wider mission.')]),
            ], ['title' => 'Our story', 'body' => 'Use these editable milestones without inventing dates or achievements.', 'content_width' => 'wide', 'grid_preset' => 'three', 'background' => 'surface_muted'], ['mobile' => ['stack' => 'stack']]),

            $this->section([
                $this->column([$this->value('heart', 'Jesus at the centre', 'Let worship, discipleship, care, and service flow from a clear commitment to following Jesus.')]),
                $this->column([$this->value('people', 'People before programmes', 'Create space for every generation to be known, supported, and invited into meaningful community.')]),
                $this->column([$this->value('hand-heart', 'Faith expressed in service', 'Carry faith into everyday life through generosity, compassion, and practical care for neighbours.')]),
            ], ['title' => 'What shapes us', 'body' => 'Review and adapt these values so they accurately express the ministry.', 'content_width' => 'wide', 'grid_preset' => 'three', 'background' => 'surface'], ['mobile' => ['stack' => 'stack']]),

            $this->section([
                $this->column([$this->element('media_text', [
                    'media_url' => '/images/pages/community-outreach.png',
                    'media_alt' => 'Adults from different generations serving neighbours at a community food distribution.',
                    'placement' => 'right',
                    'title' => 'A ministry made of people.',
                    'body' => 'The public life of the ministry includes gathering, prayer, testimony, pastoral connection, service, and generosity. Each pathway is an invitation to participate at a healthy pace.',
                    'button_label' => 'Explore departments',
                    'button_url' => '/departments',
                ], [
                    'custom_css' => 'selector .public-page-media-text { grid-template-columns: minmax(280px, .85fr) minmax(0, 1.15fr); gap: clamp(28px, 6vw, 76px); align-items: center; } selector .public-page-media-visual { order: 2; border-radius: 24px; } selector img { min-height: 430px; } selector h2 { margin: 0; color: var(--ink); font-family: var(--font-heading, Georgia, serif); font-size: clamp(2.3rem, 5vw, 4.8rem); line-height: 1; letter-spacing: -.055em; } selector p { color: var(--muted); font-size: 1.02rem; line-height: 1.8; } @media (max-width: 760px) { selector .public-page-media-text { grid-template-columns: 1fr; } selector .public-page-media-visual { order: 0; } selector img { min-height: 280px; } }',
                ])]),
            ], ['content_width' => 'wide', 'background' => 'canvas']),

            $this->section([
                $this->column([$this->element('image_card', [
                    'url' => '/images/pages/home-service-carousel.png',
                    'alt' => 'Members of the ministry serving together in their community.',
                    'title' => 'Find your place',
                    'body' => 'Explore the teams where your gifts, time, and care can make a practical difference.',
                    'button_label' => 'Explore departments',
                    'button_url' => '/departments',
                ], $this->destinationCardStyle())]),
                $this->column([$this->element('image_card', [
                    'url' => '/images/pages/home-arrival-hero.png',
                    'alt' => 'People arriving and connecting at a ministry gathering.',
                    'title' => 'Plan your visit',
                    'body' => 'See where the ministry gathers and find the practical details that make your visit simple.',
                    'button_label' => 'View locations',
                    'button_url' => '/locations',
                ], $this->destinationCardStyle())]),
            ], ['title' => 'People and places', 'body' => 'Find a meaningful place to serve or the practical details for your next visit.', 'content_width' => 'wide', 'grid_preset' => 'two', 'column_gap' => 'spacious', 'custom_css' => 'selector .public-page-structured-heading { margin-bottom: clamp(24px, 3vw, 44px); }'], ['mobile' => ['stack' => 'stack']]),

        ];

        return $this->documents->normalise(['version' => 2, 'sections' => $sections], $features);
    }

    private function milestone(string $icon, string $title, string $body): array
    {
        return $this->element('icon_box', compact('icon', 'title', 'body') + ['button_label' => '', 'button_url' => ''], $this->cardStyle());
    }

    private function value(string $icon, string $title, string $body): array
    {
        return $this->element('icon_box', compact('icon', 'title', 'body') + ['button_label' => '', 'button_url' => ''], [
            'background_color' => 'surface',
            'radius_top_left' => '22px', 'radius_top_right' => '22px',
            'radius_bottom_right' => '22px', 'radius_bottom_left' => '22px',
            'custom_css' => 'selector .public-page-icon-box { height: 100%; box-sizing: border-box; border-radius: 22px; padding: clamp(24px, 4vw, 38px); } selector .ui-icon { box-sizing: content-box; border-radius: 999px; padding: 12px; background: color-mix(in srgb, var(--action) 12%, var(--surface)); } selector h2 { font-family: var(--font-heading, Georgia, serif); font-size: clamp(1.45rem, 2.4vw, 2rem); letter-spacing: -.035em; }',
        ]);
    }

    private function cardStyle(): array
    {
        return [
            'background_color' => 'surface',
            'radius_top_left' => '22px', 'radius_top_right' => '22px',
            'radius_bottom_right' => '22px', 'radius_bottom_left' => '22px',
            'custom_css' => 'selector .public-page-icon-box, selector .public-page-callout { height: 100%; box-sizing: border-box; padding: clamp(24px, 4vw, 38px); } selector h2 { font-family: var(--font-heading, Georgia, serif); letter-spacing: -.035em; } selector a { color: var(--action); font-weight: 800; }',
        ];
    }

    private function destinationCardStyle(): array
    {
        return [
            'radius_top_left' => '22px', 'radius_top_right' => '22px',
            'radius_bottom_right' => '22px', 'radius_bottom_left' => '22px',
            'button_background' => 'action', 'button_hover_background' => 'brand',
            'button_radius' => '999px', 'button_width' => 'auto',
            'custom_css' => 'selector .public-page-image-card { height: 100%; overflow: hidden; border: 1px solid var(--line); border-radius: 24px; background: transparent !important; box-shadow: none; } selector .public-page-image-card > img { aspect-ratio: 16 / 10; border-radius: 0; } selector .public-page-image-card > div { display: grid; align-content: space-between; gap: 18px; min-height: 260px; padding: clamp(26px, 4vw, 42px); } selector .public-page-image-card h2 { max-width: 13ch; font-family: var(--font-heading, Georgia, serif); font-size: clamp(1.9rem, 3vw, 2.8rem); line-height: 1; letter-spacing: -.05em; } selector .public-page-image-card p { max-width: 42ch; line-height: 1.75; } selector .public-page-image-card .button { width: fit-content !important; margin-top: 10px; }',
        ];
    }

    private function headingStyle(): array
    {
        return ['custom_css' => 'selector { max-width: 800px; } selector h2 { color: var(--ink) !important; font-family: var(--font-heading, Georgia, serif); font-size: clamp(2.4rem, 5vw, 5rem); line-height: 1; letter-spacing: -.06em; } selector p { color: color-mix(in srgb, var(--ink) 74%, var(--canvas)) !important; font-size: 1.04rem; line-height: 1.85; }'];
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
    private function column(array $elements, array $settings = [], array $responsive = []): array
    {
        return ['id' => (string) Str::uuid(), 'settings' => $settings, 'responsive' => $responsive, 'elements' => $elements];
    }

    private function element(string $type, array $content, array $settings = [], array $responsive = []): array
    {
        return ['id' => (string) Str::uuid(), 'type' => $type, 'content' => $content, 'settings' => $settings, 'responsive' => $responsive];
    }

    private function box(string $vertical, string $horizontal): array
    {
        return ['top' => $vertical, 'right' => $horizontal, 'bottom' => $vertical, 'left' => $horizontal];
    }
}
