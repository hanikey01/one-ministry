<?php

namespace App\Services;

use Illuminate\Support\Str;

final class HomepageStarterService
{
    public function __construct(private readonly PageDocumentService $documents) {}

    public function document(array $features): array
    {
        $box = fn (string $y, string $x): array => ['top' => $y, 'right' => $x, 'bottom' => $y, 'left' => $x];
        $element = fn (string $type, array $content, array $style = []): array => [
            'id' => (string) Str::uuid(), 'type' => $type, 'content' => $content, 'settings' => $style,
            'responsive' => ['desktop' => ['padding' => $box('0px', '0px')]],
        ];
        $column = fn (array $elements, array $style = []): array => ['id' => (string) Str::uuid(), 'settings' => $style, 'responsive' => [], 'elements' => $elements];
        $section = fn (array $columns, array $style = []): array => [
            'id' => (string) Str::uuid(), 'type' => 'section',
            'settings' => $style + ['content_width' => 'wide', 'column_gap' => 'spacious'],
            'responsive' => ['desktop' => ['padding' => $box('80px', '32px')], 'tablet' => ['padding' => $box('56px', '24px')], 'mobile' => ['stack' => 'stack', 'padding' => $box('40px', '20px')]],
            'columns' => $columns,
        ];
        $cardStyle = ['background_color' => 'surface', 'radius_top_left' => '20px', 'radius_top_right' => '20px', 'radius_bottom_left' => '20px', 'radius_bottom_right' => '20px', 'custom_css' => 'selector { padding: clamp(24px, 4vw, 40px); height: 100%; box-sizing: border-box; } selector h2 { font-size: clamp(1.35rem, 2vw, 1.8rem); line-height: 1.2; }'];
        $buttonStyle = ['button_radius' => '999px', 'button_padding_x' => '26px', 'button_padding_y' => '14px'];
        $sections = [
            $section([$column([
                $element('carousel', [
                    'eyebrow' => '', 'title' => '', 'body' => '',
                    'slide_one_type' => 'flyer', 'slide_one_media_url' => '/images/home/flyer-night-of-worship.svg', 'slide_one_title' => 'A place to encounter God and find family.', 'slide_one_body' => 'Join a worshipping community where there is room for you.', 'slide_one_button_label' => 'Plan your visit', 'slide_one_button_url' => '/visit',
                    'slide_two_type' => 'flyer', 'slide_two_media_url' => '/images/home/flyer-grow-in-the-word.svg', 'slide_two_title' => 'Grow deeper in the Word.', 'slide_two_body' => 'A weekly space for Scripture, conversation, and practical faith.', 'slide_two_button_label' => 'Explore gatherings', 'slide_two_button_url' => '/events',
                    'slide_three_type' => 'flyer', 'slide_three_media_url' => '/images/home/flyer-find-your-place.svg', 'slide_three_title' => 'Find your place in the story.', 'slide_three_body' => 'Discover community, service, and a meaningful next step.', 'slide_three_button_label' => 'Find your place', 'slide_three_button_url' => '/departments',
                    'slides' => '', 'button_label' => '', 'button_url' => '',
                ], ['custom_css' => 'selector { display: block; } selector .public-page-carousel { border: 0; border-radius: 0; padding: 0; background: transparent; } selector .public-page-carousel-stage { min-height: clamp(460px, 66vw, 720px); border-radius: 0; }']),
            ])], ['content_width' => 'full', 'padding' => $box('0px', '0px'), 'custom_css' => 'selector { margin-top: 0; }']),
            $section([
                $column([$element('icon_box', ['icon' => 'people', 'title' => 'Find your community', 'body' => 'Discover places to connect, share life, and grow alongside others.', 'button_label' => 'Explore departments', 'button_url' => '/departments'], $cardStyle)]),
                $column([$element('icon_box', ['icon' => 'calendar', 'title' => 'Make room to gather', 'body' => 'See what is coming up and find a gathering that fits your next step.', 'button_label' => 'See upcoming events', 'button_url' => '/events'], $cardStyle)]),
                $column([$element('icon_box', ['icon' => 'heart', 'title' => 'Begin a conversation', 'body' => 'Have a question or want to learn more? Start with a simple hello.', 'button_label' => 'Contact us', 'button_url' => '/contact'], $cardStyle)]),
            ], ['background' => 'surface_muted']),
            $section([$column([$element('rich_text', ['title' => 'More than a Sunday. A shared journey.', 'text' => "Faith has a place in everyday life. Our gatherings are an invitation to worship, learn, and discover what it means to follow Jesus together.\n\nWhether you are exploring for the first time or looking for a community to call home, there is room to begin."], ['custom_css' => 'selector h2 { max-width: 18ch; font-size: clamp(2rem, 4vw, 3.6rem); line-height: 1.12; letter-spacing: -.03em; } selector p { max-width: 62ch; line-height: 1.85; }'])]), $column([$element('quote', ['text' => 'For where two or three are gathered together in my name, there am I in the midst of them.', 'attribution' => 'Matthew 18:20, KJV'], $cardStyle + ['background_color' => 'action', 'custom_css' => 'selector { color: var(--brand-foreground); } selector small { color: color-mix(in srgb, var(--brand-foreground) 74%, transparent); }'])], ['vertical_alignment' => 'center'])]),
            $section([$column([$element('service_times', ['title' => 'Gather with us', 'body' => 'Find a public service and choose a location for your visit.'], $cardStyle)]), $column([$element('events', ['title' => 'Coming up', 'body' => 'Explore upcoming opportunities to gather and connect.'], $cardStyle)])], ['background' => 'surface_muted']),
            $section([$column([$element('call_to_action', ['title' => 'Take your next step.', 'body' => 'Start with a visit, a conversation, or a chance to meet the community. We look forward to welcoming you.', 'button_label' => 'Plan your visit', 'button_url' => '/visit'], $buttonStyle + ['custom_css' => 'selector .public-page-cta { padding: clamp(32px, 6vw, 72px); border-radius: 24px; } selector h2 { font-size: clamp(2rem, 5vw, 4rem); line-height: 1.1; }'])])]),
        ];

        return $this->documents->normalise(['version' => 2, 'sections' => $sections], $features);
    }
}
