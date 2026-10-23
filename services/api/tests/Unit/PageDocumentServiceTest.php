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
}
