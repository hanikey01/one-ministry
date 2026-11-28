<?php

namespace App\Services;

use Illuminate\Support\Str;

final class HomepageStarterService
{
    public function __construct(private readonly PageDocumentService $documents) {}

    /** @param array<int, string> $features */
    public function document(array $features): array
    {
        $sections = [
            $this->section([
                $this->column([$this->element('carousel', [
                    'eyebrow' => '', 'title' => '', 'body' => '',
                    'slide_one_type' => 'flyer', 'slide_one_presentation' => 'editorial', 'slide_one_media_url' => '/images/pages/home-arrival-hero.png',
                    'slide_one_title' => 'A place to know God and find community.',
                    'slide_one_body' => '',
                    'slide_one_button_label' => 'View service times', 'slide_one_button_url' => '/service-times',
                    'slide_two_type' => 'flyer', 'slide_two_presentation' => 'editorial', 'slide_two_media_url' => '/images/pages/home-community-carousel.png',
                    'slide_two_title' => 'There is room for you here.',
                    'slide_two_body' => '',
                    'slide_two_button_label' => 'Explore events', 'slide_two_button_url' => '/events',
                    'slide_three_type' => 'flyer', 'slide_three_presentation' => 'editorial', 'slide_three_media_url' => '/images/pages/home-service-carousel.png',
                    'slide_three_title' => 'Faith expressed through care.',
                    'slide_three_body' => '',
                    'slide_three_button_label' => 'About us', 'slide_three_button_url' => '/about-us',
                    'slides' => '', 'button_label' => '', 'button_url' => '',
                ], [
                    'custom_css' => 'selector { display: block; } selector .public-page-carousel { border: 0; border-radius: 0; padding: 0; background: transparent; } selector .public-page-carousel-stage { min-height: clamp(380px, 46vw, 540px); border: 0; border-radius: 0; touch-action: pan-y; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay { justify-content: end; align-items: center; text-align: center; padding: clamp(30px, 4vw, 50px); background: linear-gradient(0deg, color-mix(in srgb, var(--brand) 72%, transparent) 0%, color-mix(in srgb, var(--brand) 24%, transparent) 48%, transparent 76%); } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay > div { justify-items: center; max-width: min(90%, 720px); } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay h3 { max-width: 16ch; font-size: clamp(2.15rem, 4vw, 3.45rem); line-height: 1; text-wrap: balance; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay p { display: none; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay .button { align-self: center; box-sizing: border-box; border: 2px solid #ffffff !important; border-radius: 999px !important; padding: 12px 23px; background: rgb(0 18 20 / 38%) !important; color: #ffffff !important; box-shadow: 0 12px 28px rgb(0 0 0 / 24%); backdrop-filter: blur(7px); } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay .button:hover, selector .public-page-carousel-slide--editorial .public-page-carousel-overlay .button:focus-visible { background: #ffffff !important; color: var(--brand) !important; transform: translateY(-1px); } @media (max-width: 980px) { selector .public-page-carousel-stage { min-height: 460px; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay { padding: 34px 30px; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay h3 { font-size: clamp(2rem, 5.4vw, 3rem); } } @media (max-width: 620px) { selector .public-page-carousel-stage { min-height: 430px; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay { padding: 26px 22px 34px; background: linear-gradient(0deg, color-mix(in srgb, var(--brand) 74%, transparent) 0%, color-mix(in srgb, var(--brand) 20%, transparent) 54%, transparent 78%); } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay h3 { max-width: 15ch; font-size: clamp(1.8rem, 7vw, 2.25rem); line-height: 1.02; } selector .public-page-carousel-slide--editorial .public-page-carousel-overlay .button { padding: 11px 20px; } }',
                ])]),
            ], ['content_width' => 'full', 'padding' => $this->box('0px', '0px'), 'overflow' => 'hidden', 'custom_class' => 'homepage-hero-section']),

            $this->section([
                $this->column([$this->element('rich_text', [
                    'title' => 'There is a next step for you.',
                    'text' => 'Whether you need prayer, want to share what God has done, have a question, or are ready to serve, begin with the path that fits today.',
                ], $this->sectionHeadingStyle())]),
            ], ['content_width' => 'standard', 'background' => 'canvas']),

            $this->section([
                $this->column([$this->pathway('hand-heart', 'Submit a prayer request', 'Share a private prayer request with the care team.', 'Ask for prayer', '/member/prayer')]),
                $this->column([$this->pathway('message', 'Share your testimony', 'Tell the story of what God has done in your life.', 'Share your story', '/testimonies')]),
                $this->column([$this->pathway('people', 'Join a department', 'Find a team where your gifts and availability can serve others.', 'Explore departments', '/departments')]),
                $this->column([$this->pathway('heart', 'Contact the ministry', 'Start a conversation and let the right team follow up with you.', 'Contact us', '/contact')]),
            ], ['content_width' => 'wide', 'grid_preset' => 'four', 'column_gap' => 'regular', 'background' => 'canvas'], ['tablet' => ['column_count' => '2'], 'mobile' => ['stack' => 'stack']]),

            $this->section([
                $this->column([$this->element('events', [
                    'title' => 'What is happening next',
                    'body' => 'Discover current gatherings and opportunities to participate.',
                ], $this->dynamicCardStyle())]),
                $this->column([$this->element('locations', [
                    'title' => 'Find a location',
                    'body' => 'See public locations and the practical details for your visit.',
                    'button_label' => 'View locations',
                    'button_url' => '/locations',
                ], $this->dynamicCardStyle())]),
            ], ['title' => 'Gather, grow, and serve', 'body' => 'Current events and public locations appear here automatically as staff publish them.', 'content_width' => 'wide', 'grid_preset' => 'two', 'column_gap' => 'regular', 'background' => 'surface_muted'], ['mobile' => ['stack' => 'stack']]),

            $this->section([
                $this->column([$this->element('media_text', [
                    'media_url' => '/images/pages/community-outreach.png',
                    'media_alt' => 'A diverse group of adults serving neighbours at a community food distribution.',
                    'placement' => 'left',
                    'title' => 'Discover the story behind the welcome.',
                    'body' => 'Every ministry is shaped by a calling, a community, and a commitment to serve. Learn more about the values and people that guide this ministry’s public life.',
                    'button_label' => 'About us',
                    'button_url' => '/about-us',
                ], [
                    'custom_css' => 'selector .public-page-media-text { grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr); gap: clamp(28px, 6vw, 76px); align-items: center; } selector .public-page-media-visual { border-radius: 24px; } selector img { min-height: 420px; } selector h2 { margin: 0; color: var(--ink); font-family: var(--font-heading, Georgia, serif); font-size: clamp(2rem, 3.2vw, 3.4rem); line-height: 1.04; letter-spacing: -.05em; } selector p { color: var(--muted); font-size: 1.02rem; line-height: 1.8; } @media (max-width: 760px) { selector .public-page-media-text { grid-template-columns: 1fr; } selector img { min-height: 280px; } }',
                ])]),
            ], ['content_width' => 'wide', 'background' => 'surface']),

        ];

        if (in_array('giving', $features, true)) {
            $sections[] = $this->givingSection();
        }

        return $this->documents->normalise(['version' => 2, 'sections' => $sections], $features);
    }

    private function givingSection(): array
    {
        return $this->section([
            $this->column([$this->element('media_text', [
                'media_url' => '/images/pages/giving-community-support.png',
                'media_alt' => 'Volunteers sharing fresh groceries with a neighbour at a community support table.',
                'placement' => 'right',
                'title' => 'Give with purpose',
                'body' => 'Support the ministry through its currently available giving methods and published funds.',
                'button_label' => 'Give securely',
                'button_url' => '/give',
            ], [
                'custom_css' => 'selector .public-page-media-text { grid-template-columns: minmax(280px, .85fr) minmax(0, 1.15fr); gap: clamp(28px, 6vw, 76px); align-items: center; } selector .public-page-media-visual { order: 2; aspect-ratio: 16 / 9; border-radius: 24px; } selector img { width: 100%; height: 100%; min-height: 0; object-fit: cover; } selector h2 { margin: 0; color: var(--ink); font-family: var(--font-heading, Georgia, serif); font-size: clamp(2rem, 3.2vw, 3.4rem); line-height: 1.04; letter-spacing: -.05em; } selector p { color: var(--muted); font-size: 1.02rem; line-height: 1.8; } selector .button { width: fit-content; margin-top: 10px; border-radius: 999px; } @media (max-width: 760px) { selector .public-page-media-text { grid-template-columns: 1fr; } selector .public-page-media-visual { order: 0; } }',
            ])]),
        ], ['content_width' => 'wide', 'background' => 'canvas']);
    }

    private function pathway(string $icon, string $title, string $body, string $label, string $url): array
    {
        return $this->element('icon_box', compact('icon', 'title', 'body') + ['button_label' => $label, 'button_url' => $url], [
            'background_color' => 'surface',
            'button_background' => 'action',
            'button_hover_background' => 'brand',
            'button_radius' => '999px',
            'custom_css' => 'selector .public-page-icon-box { height: 100%; box-sizing: border-box; border-radius: 20px; padding: clamp(22px, 3vw, 32px); box-shadow: 0 14px 40px var(--shadow-soft); } selector .ui-icon { box-sizing: content-box; border-radius: 14px; padding: 11px; background: color-mix(in srgb, var(--action) 12%, var(--surface)); } selector h2 { font-family: var(--font-heading, Georgia, serif); font-size: clamp(1.25rem, 2vw, 1.65rem); letter-spacing: -.03em; } selector .button { width: max-content; margin-top: 8px; }',
        ]);
    }

    private function dynamicCardStyle(): array
    {
        return [
            'background_color' => 'surface',
            'radius_top_left' => '20px', 'radius_top_right' => '20px',
            'radius_bottom_right' => '20px', 'radius_bottom_left' => '20px',
            'button_radius' => '999px',
            'custom_css' => 'selector .public-page-callout { height: 100%; box-sizing: border-box; padding: clamp(24px, 3vw, 34px); } selector h2 { font-family: var(--font-heading, Georgia, serif); font-size: clamp(1.35rem, 2.2vw, 1.9rem); letter-spacing: -.035em; } selector a { color: var(--action); font-weight: 800; }',
        ];
    }

    private function sectionHeadingStyle(): array
    {
        return ['custom_css' => 'selector { max-width: 780px; } selector h2 { color: var(--ink) !important; font-family: var(--font-heading, Georgia, serif); font-size: clamp(2.35rem, 5vw, 4.9rem); line-height: 1; letter-spacing: -.06em; } selector p { color: color-mix(in srgb, var(--ink) 74%, var(--canvas)) !important; font-size: 1.04rem; line-height: 1.8; }'];
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
