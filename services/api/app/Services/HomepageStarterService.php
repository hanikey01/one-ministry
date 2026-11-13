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
                $element('hero', ['eyebrow' => 'WELCOME HOME', 'title' => 'Faith. Community. A place to belong.', 'body' => 'Come as you are. Find space to worship, ask questions, grow in faith, and build meaningful relationships.', 'button_label' => 'Plan your visit', 'button_url' => '/visit'], $buttonStyle + [
                    'text_color' => '#ffffff', 'button_background' => 'surface', 'button_text_color' => '#17324d',
                    'custom_css' => 'selector .public-page-hero { border: 0; background: transparent; padding: clamp(24px, 6vw, 76px) 0; } selector h1 { max-width: 13ch; font-size: clamp(2.8rem, 7vw, 6.2rem); line-height: 1.02; letter-spacing: -.045em; color: inherit; } selector p { max-width: 52ch; font-size: clamp(1.05rem, 2vw, 1.25rem); line-height: 1.75; color: inherit; } selector small { letter-spacing: .16em; color: inherit; }',
                ]),
            ], ['vertical_alignment' => 'center']), $column([
                $element('rich_text', ['title' => 'Your first visit starts here.', 'text' => "Discover our public service times and locations.\n\nFind practical information, meet the community, and take your next step at your own pace."], $cardStyle + ['text_color' => '#17324d']),
                $element('button', ['label' => 'Find a location', 'url' => '/locations'], $buttonStyle + ['button_background' => 'surface', 'button_text_color' => '#17324d']),
            ], ['vertical_alignment' => 'center', 'element_gap' => '24px'])], ['background_color' => '#17324d', 'min_height' => '70vh', 'vertical_alignment' => 'center', 'custom_css' => 'selector { background: radial-gradient(ellipse at 90% 10%, rgb(75 145 139 / 45%), transparent 55%), #17324d; }']),
            $section([
                $column([$element('icon_box', ['icon' => 'people', 'title' => 'Find your community', 'body' => 'Discover places to connect, share life, and grow alongside others.', 'button_label' => 'Explore departments', 'button_url' => '/departments'], $cardStyle)]),
                $column([$element('icon_box', ['icon' => 'calendar', 'title' => 'Make room to gather', 'body' => 'See what is coming up and find a gathering that fits your next step.', 'button_label' => 'See upcoming events', 'button_url' => '/events'], $cardStyle)]),
                $column([$element('icon_box', ['icon' => 'heart', 'title' => 'Begin a conversation', 'body' => 'Have a question or want to learn more? Start with a simple hello.', 'button_label' => 'Contact us', 'button_url' => '/contact'], $cardStyle)]),
            ], ['background' => 'surface_muted']),
            $section([$column([$element('rich_text', ['title' => 'More than a Sunday. A shared journey.', 'text' => "Faith has a place in everyday life. Our gatherings are an invitation to worship, learn, and discover what it means to follow Jesus together.\n\nWhether you are exploring for the first time or looking for a community to call home, there is room to begin."], ['custom_css' => 'selector h2 { max-width: 18ch; font-size: clamp(2rem, 4vw, 3.6rem); line-height: 1.12; letter-spacing: -.03em; } selector p { max-width: 62ch; line-height: 1.85; }'])]), $column([$element('quote', ['text' => 'For where two or three are gathered together in my name, there am I in the midst of them.', 'attribution' => 'Matthew 18:20, KJV'], $cardStyle + ['background_color' => '#17324d', 'text_color' => '#ffffff'])], ['vertical_alignment' => 'center'])]),
            $section([$column([$element('service_times', ['title' => 'Gather with us', 'body' => 'Find a public service and choose a location for your visit.'], $cardStyle)]), $column([$element('events', ['title' => 'Coming up', 'body' => 'Explore upcoming opportunities to gather and connect.'], $cardStyle)])], ['background' => 'surface_muted']),
            $section([$column([$element('call_to_action', ['title' => 'Take your next step.', 'body' => 'Start with a visit, a conversation, or a chance to meet the community. We look forward to welcoming you.', 'button_label' => 'Plan your visit', 'button_url' => '/visit'], $buttonStyle + ['custom_css' => 'selector .public-page-cta { padding: clamp(32px, 6vw, 72px); border-radius: 24px; } selector h2 { font-size: clamp(2rem, 5vw, 4rem); line-height: 1.1; }'])])]),
        ];

        return $this->documents->normalise(['version' => 2, 'sections' => $sections], $features);
    }
}
