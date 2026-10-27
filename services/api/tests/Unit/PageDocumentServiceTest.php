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
}
