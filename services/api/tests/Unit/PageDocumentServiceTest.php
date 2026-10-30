<?php

namespace Tests\Unit;

use App\Services\PageDocumentService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PageDocumentServiceTest extends TestCase
{
    public function test_legacy_media_and_text_becomes_one_two_column_section(): void
    {
        $document = app(PageDocumentService::class)->fromFlat([[
            'type' => 'media_text',
            'data' => ['media_url' => 'https://cdn.example.test/welcome.jpg', 'media_alt' => 'People talking', 'title' => 'Welcome', 'body' => 'Come as you are.', 'button_label' => 'Visit', 'button_url' => '/visit'],
        ]]);

        $this->assertSame(2, $document['version']);
        $this->assertCount(1, $document['sections']);
        $this->assertCount(2, $document['sections'][0]['columns']);
        $this->assertSame('image', $document['sections'][0]['columns'][0]['elements'][0]['type']);
        $this->assertSame('call_to_action', $document['sections'][0]['columns'][1]['elements'][0]['type']);
    }

    public function test_structured_documents_keep_valid_editor_ids_and_reject_unsafe_elements(): void
    {
        $service = app(PageDocumentService::class);
        $document = [
            'version' => 2,
            'sections' => [[
                'id' => 'section-home', 'type' => 'section', 'settings' => [], 'responsive' => [],
                'columns' => [[
                    'id' => 'column-main', 'settings' => [], 'responsive' => [],
                    'elements' => [[
                        'id' => 'element-copy', 'type' => 'paragraph', 'content' => ['text' => 'Welcome'], 'settings' => [], 'responsive' => [],
                    ]],
                ]],
            ]],
        ];

        $this->assertSame('element-copy', $service->normalise($document)['sections'][0]['columns'][0]['elements'][0]['id']);
        $document['sections'][0]['columns'][0]['elements'][0]['type'] = 'script';
        $this->expectException(ValidationException::class);
        $service->normalise($document);
    }

    public function test_alert_progress_and_table_elements_have_bounded_content_contracts(): void
    {
        $document = [
            'version' => 2,
            'sections' => [[
                'id' => 'section-content', 'type' => 'section', 'settings' => [], 'responsive' => [],
                'columns' => [[
                    'id' => 'column-main', 'settings' => [], 'responsive' => [],
                    'elements' => [
                        ['id' => 'alert', 'type' => 'alert', 'content' => ['tone' => 'warning', 'title' => 'Weather update', 'body' => 'Please allow extra travel time.'], 'settings' => [], 'responsive' => []],
                        ['id' => 'progress', 'type' => 'progress', 'content' => ['title' => 'Fundraising', 'label' => 'Raised', 'value' => '75'], 'settings' => [], 'responsive' => []],
                        ['id' => 'table', 'type' => 'table', 'content' => ['title' => 'Service times', 'headers' => 'Day|Time', 'rows' => "Sunday|10:00 AM\nWednesday|7:00 PM"], 'settings' => [], 'responsive' => []],
                    ],
                ]],
            ]],
        ];

        $elements = app(PageDocumentService::class)->normalise($document)['sections'][0]['columns'][0]['elements'];

        $this->assertSame('warning', $elements[0]['content']['tone']);
        $this->assertSame('75', $elements[1]['content']['value']);
        $this->assertSame('Day|Time', $elements[2]['content']['headers']);
    }

    public function test_progress_and_table_elements_reject_invalid_values(): void
    {
        $service = app(PageDocumentService::class);
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-content', 'type' => 'section', 'settings' => [], 'responsive' => [],
            'columns' => [[
                'id' => 'column-main', 'settings' => [], 'responsive' => [],
                'elements' => [['id' => 'progress', 'type' => 'progress', 'content' => ['title' => '', 'label' => '', 'value' => '101'], 'settings' => [], 'responsive' => []]],
            ]],
        ]]];

        try {
            $service->normalise($document);
            $this->fail('Expected an invalid progress value to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $document['sections'][0]['columns'][0]['elements'][0] = ['id' => 'table', 'type' => 'table', 'content' => ['title' => '', 'headers' => 'Day|Time', 'rows' => 'Sunday'], 'settings' => [], 'responsive' => []];
        $this->expectException(ValidationException::class);
        $service->normalise($document);
    }

    public function test_alert_rejects_an_unapproved_tone(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-content', 'type' => 'section', 'settings' => [], 'responsive' => [],
            'columns' => [[
                'id' => 'column-main', 'settings' => [], 'responsive' => [],
                'elements' => [['id' => 'alert', 'type' => 'alert', 'content' => ['tone' => 'danger', 'title' => '', 'body' => ''], 'settings' => [], 'responsive' => []]],
            ]],
        ]]];

        $this->expectException(ValidationException::class);
        app(PageDocumentService::class)->normalise($document);
    }

    public function test_page_objects_only_accept_bounded_overflow_settings(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-content', 'type' => 'section', 'settings' => ['overflow' => 'hidden'], 'responsive' => [],
            'columns' => [[
                'id' => 'column-main', 'settings' => ['overflow' => 'visible'], 'responsive' => [],
                'elements' => [['id' => 'paragraph', 'type' => 'paragraph', 'content' => ['text' => 'Welcome'], 'settings' => ['overflow' => 'hidden'], 'responsive' => []]],
            ]],
        ]]];

        $normalised = app(PageDocumentService::class)->normalise($document);
        $this->assertSame('hidden', $normalised['sections'][0]['settings']['overflow']);
        $this->assertSame('visible', $normalised['sections'][0]['columns'][0]['settings']['overflow']);

        $document['sections'][0]['settings']['overflow'] = 'scroll';
        $this->expectException(ValidationException::class);
        app(PageDocumentService::class)->normalise($document);
    }

    public function test_responsive_per_side_spacing_survives_normalisation(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-spacing', 'type' => 'section', 'settings' => [],
            'responsive' => ['desktop' => [
                'padding' => ['top' => '24px', 'right' => '2rem', 'bottom' => '24px', 'left' => '2rem'],
                'margin' => ['top' => '-8px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px'],
            ]],
            'columns' => [[
                'id' => 'column-main', 'settings' => [], 'responsive' => [],
                'elements' => [['id' => 'paragraph', 'type' => 'paragraph', 'content' => ['text' => 'Welcome'], 'settings' => [], 'responsive' => []]],
            ]],
        ]]];

        $responsive = app(PageDocumentService::class)->normalise($document)['sections'][0]['responsive']['desktop'];

        $this->assertSame('2rem', $responsive['padding']['right']);
        $this->assertSame('-8px', $responsive['margin']['top']);
    }

    public function test_section_column_and_element_design_settings_survive_normalisation(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-design', 'type' => 'section',
            'settings' => ['custom_id' => 'welcome-section', 'custom_class' => 'featured-section', 'custom_css' => 'selector { color: #112233; }', 'custom_js' => 'selector.dataset.ready = "yes";', 'radius_top_left' => '12px'],
            'responsive' => ['mobile' => ['alignment' => 'center']],
            'columns' => [[
                'id' => 'column-design',
                'settings' => ['custom_id' => 'welcome-column', 'custom_class' => 'featured-column', 'custom_css' => 'padding-inline: 1rem;', 'custom_js' => 'selector.dataset.column = "ready";'],
                'responsive' => ['desktop' => ['padding' => ['top' => '10px', 'right' => '2rem', 'bottom' => '10px', 'left' => '2rem']]],
                'elements' => [[
                    'id' => 'button-design', 'type' => 'button', 'content' => ['label' => 'Welcome', 'url' => ''],
                    'settings' => ['custom_id' => 'welcome-button', 'custom_class' => 'featured-button', 'font_size' => '18px', 'button_padding_x' => '22px', 'button_radius' => '999px'],
                    'responsive' => ['tablet' => ['margin' => ['top' => '4px', 'right' => '0px', 'bottom' => '4px', 'left' => '0px']]],
                ]],
            ]],
        ]]];

        $normalised = app(PageDocumentService::class)->normalise($document);
        $section = $normalised['sections'][0];
        $column = $section['columns'][0];
        $element = $column['elements'][0];

        $this->assertSame('selector { color: #112233; }', $section['settings']['custom_css']);
        $this->assertSame('welcome-column', $column['settings']['custom_id']);
        $this->assertSame('2rem', $column['responsive']['desktop']['padding']['right']);
        $this->assertSame('999px', $element['settings']['button_radius']);
        $this->assertSame('4px', $element['responsive']['tablet']['margin']['top']);
    }

    public function test_custom_code_element_preserves_bounded_html_css_and_javascript(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-code', 'type' => 'section', 'settings' => [], 'responsive' => ['desktop' => ['margin' => 'compact']],
            'columns' => [[
                'id' => 'column-main', 'settings' => [], 'responsive' => [],
                'elements' => [[
                    'id' => 'custom-code', 'type' => 'custom_code',
                    'content' => ['html' => '<button type="button">Open</button>', 'css' => 'button { color: rebeccapurple; }', 'javascript' => 'document.querySelector("button")?.focus();', 'fallback' => 'Custom content is available.'],
                    'settings' => ['custom_id' => 'welcome-card', 'custom_class' => 'featured-card compact'], 'responsive' => [],
                ]],
            ]],
        ]]];

        $normalised = app(PageDocumentService::class)->normalise($document);

        $this->assertSame('<button type="button">Open</button>', $normalised['sections'][0]['columns'][0]['elements'][0]['content']['html']);
        $this->assertSame('button { color: rebeccapurple; }', $normalised['sections'][0]['columns'][0]['elements'][0]['content']['css']);
        $this->assertSame('compact', $normalised['sections'][0]['responsive']['desktop']['margin']);
        $this->assertSame('welcome-card', $normalised['sections'][0]['columns'][0]['elements'][0]['settings']['custom_id']);
    }

    public function test_section_and_column_layout_controls_survive_normalisation(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-layout', 'type' => 'section',
            'settings' => [
                'content_width' => 'custom', 'content_max_width' => '1040px', 'min_height' => '80vh',
                'vertical_alignment' => 'center', 'row_gap' => '2rem', 'background_position' => 'right_center',
                'background_size' => 'contain', 'background_repeat' => 'no_repeat', 'background_attachment' => 'scroll',
            ],
            'responsive' => ['tablet' => ['column_count' => '2']],
            'columns' => [[
                'id' => 'column-layout',
                'settings' => ['min_height' => '320px', 'vertical_alignment' => 'end', 'element_gap' => '1.5rem'],
                'responsive' => [
                    'desktop' => ['width' => 'custom', 'custom_width' => '62%'],
                    'mobile' => ['width' => 'full', 'order' => '2'],
                ],
                'elements' => [['id' => 'copy', 'type' => 'paragraph', 'content' => ['text' => 'Welcome'], 'settings' => [], 'responsive' => []]],
            ]],
        ]]];

        $normalised = app(PageDocumentService::class)->normalise($document);
        $section = $normalised['sections'][0];
        $column = $section['columns'][0];

        $this->assertSame('1040px', $section['settings']['content_max_width']);
        $this->assertSame('right_center', $section['settings']['background_position']);
        $this->assertSame('320px', $column['settings']['min_height']);
        $this->assertSame('62%', $column['responsive']['desktop']['custom_width']);
        $this->assertSame('full', $column['responsive']['mobile']['width']);
    }

    public function test_responsive_typography_is_bounded_and_preserved(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-type', 'type' => 'section', 'settings' => [], 'responsive' => [],
            'columns' => [[
                'id' => 'column-type', 'settings' => [], 'responsive' => [],
                'elements' => [[
                    'id' => 'heading-type', 'type' => 'heading', 'content' => ['text' => 'Welcome'], 'settings' => [],
                    'responsive' => [
                        'desktop' => ['font_size' => '48px', 'line_height' => '1.1', 'letter_spacing' => '-1px'],
                        'mobile' => ['font_size' => '30px', 'line_height' => '1.2'],
                    ],
                ]],
            ]],
        ]]];

        $responsive = app(PageDocumentService::class)->normalise($document)['sections'][0]['columns'][0]['elements'][0]['responsive'];

        $this->assertSame('48px', $responsive['desktop']['font_size']);
        $this->assertSame('-1px', $responsive['desktop']['letter_spacing']);
        $this->assertSame('30px', $responsive['mobile']['font_size']);
    }

    public function test_internal_ministry_media_path_is_valid_for_backgrounds(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-media', 'type' => 'section',
            'settings' => ['background_image' => '/media/8c2738b3-8de1-46c6-987b-f51f51649435?variant=public'],
            'responsive' => [],
            'columns' => [[
                'id' => 'column-main', 'settings' => [], 'responsive' => [],
                'elements' => [['id' => 'copy', 'type' => 'paragraph', 'content' => ['text' => 'Welcome'], 'settings' => [], 'responsive' => []]],
            ]],
        ]]];

        $normalised = app(PageDocumentService::class)->normalise($document);

        $this->assertSame('/media/8c2738b3-8de1-46c6-987b-f51f51649435?variant=public', $normalised['sections'][0]['settings']['background_image']);
    }
}
