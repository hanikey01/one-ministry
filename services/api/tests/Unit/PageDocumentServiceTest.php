<?php

namespace Tests\Unit;

use App\Services\PageDocumentService;
use App\Services\PageElementRegistry;
use App\Services\HomepageStarterService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PageDocumentServiceTest extends TestCase
{
    public function test_element_registry_is_the_source_of_truth_for_supported_types_and_feature_gates(): void
    {
        $registry = app(PageElementRegistry::class);

        $this->assertContains('statistics', array_keys($registry->all()));
        $this->assertSame(['title', 'stat_one_value', 'stat_one_label', 'stat_two_value', 'stat_two_label', 'stat_three_value', 'stat_three_label'], $registry->fields('statistics'));
        $this->assertSame('giving', $registry->feature('giving'));
        $this->assertFalse($registry->supports('unsupported_element'));
        $this->assertSame('Statistics', $registry->all()['statistics']['label']);
        $this->assertSame('text', $registry->all()['statistics']['category']);
        $this->assertArrayHasKey('defaults', $registry->all()['statistics']);
        $this->assertArrayHasKey('field_definitions', $registry->all()['statistics']);
        $this->assertArrayHasKey('settings', $registry->all()['statistics']);
        $this->assertArrayHasKey('responsive', $registry->all()['statistics']);
        $this->assertArrayHasKey('ai_guidance', $registry->all()['statistics']);
        $this->assertArrayNotHasKey('giving', $registry->available([]));
        $this->assertArrayHasKey('giving', $registry->available(['giving']));
    }

    public function test_every_registered_element_has_a_complete_capability_and_renderer_contract(): void
    {
        $registry = app(PageElementRegistry::class);
        $renderer = file_get_contents(resource_path('views/public/partials/page-element.blade.php'));

        foreach ($registry->all() as $type => $definition) {
            foreach (['label','category','fields','field_definitions','defaults','settings','setting_definitions','responsive','responsive_definitions','validation','accessibility','renderer','inspector','purpose','use_cases','factual_restrictions','examples','custom_code','ai_guidance'] as $key) {
                $this->assertArrayHasKey($key, $definition, "{$type} is missing {$key}");
            }
            $this->assertNotEmpty($definition['purpose'], "{$type} needs a purpose");
            $this->assertNotEmpty($definition['accessibility'], "{$type} needs accessibility rules");
            $this->assertNotEmpty($definition['use_cases'], "{$type} needs use cases");
            $this->assertNotEmpty($definition['examples'], "{$type} needs an example");
            $this->assertSame($type, $definition['renderer']['case']);
            $this->assertStringContainsString("@case('{$type}')", $renderer, "{$type} has no renderer case");
            foreach ($definition['fields'] as $field) {
                $this->assertArrayHasKey($field, $definition['field_definitions']);
                $this->assertArrayHasKey('validation', $definition['field_definitions'][$field]);
            }
        }
    }

    public function test_registry_rejects_unknown_element_fields_settings_and_missing_custom_fallback(): void
    {
        $service = app(PageDocumentService::class);
        $element = ['id'=>'copy','type'=>'paragraph','content'=>['text'=>'Welcome','invented'=>'No'],'settings'=>[],'responsive'=>[]];
        $document = ['version'=>2,'sections'=>[['id'=>'section','type'=>'section','settings'=>[],'responsive'=>[],'columns'=>[['id'=>'column','settings'=>[],'responsive'=>[],'elements'=>[$element]]]]]];

        try { $service->normalise($document); $this->fail('Unknown content fields must be rejected.'); } catch (ValidationException) { $this->assertTrue(true); }
        unset($document['sections'][0]['columns'][0]['elements'][0]['content']['invented']);
        $document['sections'][0]['columns'][0]['elements'][0]['settings']['invented_style'] = 'yes';
        try { $service->normalise($document); $this->fail('Unknown design settings must be rejected.'); } catch (ValidationException) { $this->assertTrue(true); }
        $document['sections'][0]['columns'][0]['elements'][0] = ['id'=>'code','type'=>'custom_code','content'=>['reason'=>'','html'=>'<p>Hello</p>','css'=>'','javascript'=>'','fallback'=>''],'settings'=>[],'responsive'=>[]];
        $this->expectException(ValidationException::class);
        $service->normalise($document);
    }

    public function test_section_and_column_contracts_reject_unknown_settings_and_main_page_javascript(): void
    {
        $service=app(PageDocumentService::class);
        $document=['version'=>2,'sections'=>[['id'=>'section','type'=>'section','settings'=>['invented'=>'value'],'responsive'=>[],'columns'=>[['id'=>'column','settings'=>[],'responsive'=>[],'elements'=>[]]]]]];
        try { $service->normalise($document); $this->fail('Unknown section settings must be rejected.'); } catch (ValidationException) { $this->assertTrue(true); }
        $document['sections'][0]['settings']=[];
        $document['sections'][0]['columns'][0]['settings']=['custom_js'=>'fetch("/private")'];
        $this->expectException(ValidationException::class);
        $service->normalise($document);
    }

    public function test_document_ids_must_be_unique_across_the_complete_hierarchy(): void
    {
        $this->expectException(ValidationException::class);
        app(PageDocumentService::class)->normalise(['version'=>2,'sections'=>[['id'=>'same','type'=>'section','settings'=>[],'responsive'=>[],'columns'=>[['id'=>'same','settings'=>[],'responsive'=>[],'elements'=>[]]]]]]);
    }

    public function test_only_version_two_structured_documents_are_accepted(): void
    {
        $this->expectException(ValidationException::class);
        app(PageDocumentService::class)->normalise([[
            'type' => 'media_text',
            'data' => ['media_url' => 'https://cdn.example.test/welcome.jpg', 'media_alt' => 'People talking', 'title' => 'Welcome', 'body' => 'Come as you are.', 'button_label' => 'Visit', 'button_url' => '/visit'],
        ]]);
    }

    public function test_plain_text_summary_is_extracted_without_flattening_the_document(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-summary', 'type' => 'section', 'settings' => ['title' => 'Visit us'], 'responsive' => [],
            'columns' => [[
                'id' => 'column-summary', 'settings' => [], 'responsive' => [],
                'elements' => [[
                    'id' => 'summary-copy', 'type' => 'call_to_action',
                    'content' => ['title' => 'You are welcome', 'body' => 'Join us this Sunday.', 'button_label' => 'Plan a visit', 'button_url' => '/visit'],
                    'settings' => [], 'responsive' => [],
                ]],
            ]],
        ]]];

        $summary = app(PageDocumentService::class)->plainText($document);

        $this->assertStringContainsString('Visit us', $summary);
        $this->assertStringContainsString('Join us this Sunday.', $summary);
        $this->assertStringNotContainsString('/visit', $summary);
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

    public function test_carousel_supports_a_flyer_uploaded_video_and_video_link_with_per_slide_actions(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'homepage-lead', 'type' => 'section', 'settings' => [], 'responsive' => [],
            'columns' => [[
                'id' => 'lead-column', 'settings' => [], 'responsive' => [],
                'elements' => [[
                    'id' => 'lead-carousel', 'type' => 'carousel', 'settings' => [], 'responsive' => [],
                    'content' => [
                        'eyebrow' => '', 'title' => '', 'body' => '', 'slides' => '', 'button_label' => '', 'button_url' => '',
                        'slide_one_type' => 'flyer', 'slide_one_media_url' => 'https://cdn.example.test/flyer.jpg', 'slide_one_title' => 'Welcome', 'slide_one_body' => 'A welcome flyer', 'slide_one_button_label' => 'Plan a visit', 'slide_one_button_url' => '/visit',
                        'slide_two_type' => 'video_upload', 'slide_two_media_url' => 'https://cdn.example.test/worship.mp4', 'slide_two_title' => 'Worship', 'slide_two_body' => 'An uploaded video', 'slide_two_button_label' => 'Watch', 'slide_two_button_url' => '/worship',
                        'slide_three_type' => 'video_link', 'slide_three_media_url' => 'https://www.youtube.com/watch?v=6CKCThJB5w0', 'slide_three_title' => 'Message', 'slide_three_body' => 'An external video', 'slide_three_button_label' => 'Explore', 'slide_three_button_url' => '/sermons',
                    ],
                ]],
            ]],
        ]]];

        $carousel = app(PageDocumentService::class)->normalise($document)['sections'][0]['columns'][0]['elements'][0]['content'];

        $this->assertSame('video_upload', $carousel['slide_two_type']);
        $this->assertSame('video_link', $carousel['slide_three_type']);
        $this->assertSame('/sermons', $carousel['slide_three_button_url']);
    }

    public function test_homepage_starter_begins_with_a_three_slide_theme_aware_native_carousel(): void
    {
        $document = app(HomepageStarterService::class)->document([]);
        $lead = $document['sections'][0]['columns'][0]['elements'][0];

        $this->assertSame('carousel', $lead['type']);
        $this->assertSame('/images/home/flyer-night-of-worship.svg', $lead['content']['slide_one_media_url']);
        $this->assertSame('/images/home/flyer-grow-in-the-word.svg', $lead['content']['slide_two_media_url']);
        $this->assertSame('/images/home/flyer-find-your-place.svg', $lead['content']['slide_three_media_url']);
        $this->assertSame('/departments', $lead['content']['slide_three_button_url']);
        $this->assertStringContainsString('public-page-carousel', $lead['settings']['custom_css']);
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
            'settings' => ['custom_id' => 'welcome-section', 'custom_class' => 'featured-section', 'custom_css' => 'selector { color: #112233; }', 'radius_top_left' => '12px'],
            'responsive' => ['mobile' => ['alignment' => 'center']],
            'columns' => [[
                'id' => 'column-design',
                'settings' => ['custom_id' => 'welcome-column', 'custom_class' => 'featured-column', 'custom_css' => 'padding-inline: 1rem;'],
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
                    'content' => ['reason' => 'A native element cannot provide the required isolated interaction.', 'html' => '<button type="button">Open</button>', 'css' => 'button { color: rebeccapurple; }', 'javascript' => 'document.querySelector("button")?.focus();', 'fallback' => 'Custom content is available.'],
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

    public function test_custom_code_rejects_network_storage_dynamic_code_and_unbounded_execution(): void
    {
        $base=['version'=>2,'sections'=>[['id'=>'section','type'=>'section','settings'=>[],'responsive'=>[],'columns'=>[['id'=>'column','settings'=>[],'responsive'=>[],'elements'=>[['id'=>'code','type'=>'custom_code','content'=>['reason'=>'No native element fits.','html'=>'<p>Hello</p>','css'=>'','javascript'=>'','fallback'=>'Hello'],'settings'=>[],'responsive'=>[]]]]]]]];
        foreach(['fetch("/private")','localStorage.setItem("x","y")','eval("alert(1)")','while(true){}','setInterval(()=>{},1)'] as $javascript){
            $document=$base; $document['sections'][0]['columns'][0]['elements'][0]['content']['javascript']=$javascript;
            try { app(PageDocumentService::class)->normalise($document); $this->fail('Unsafe custom JavaScript must be rejected.'); } catch (ValidationException) { $this->assertTrue(true); }
        }
    }

    public function test_custom_code_accepts_internal_and_external_media_urls(): void
    {
        $document=['version'=>2,'sections'=>[['id'=>'section','type'=>'section','settings'=>[],'responsive'=>[],'columns'=>[['id'=>'column','settings'=>[],'responsive'=>[],'elements'=>[['id'=>'code','type'=>'custom_code','content'=>[
            'reason'=>'No native element fits.',
            'html'=>'<a href="https://example.com/details">Details</a><img src="/media/019abcde-1234-7000-8000-abcdefabcdef"><video src="https://cdn.example.com/message.mp4" controls></video><iframe src="https://www.youtube.com/embed/example" title="Message"></iframe>',
            'css'=>'.hero{background-image:url("https://images.example.com/hero.jpg")}',
            'javascript'=>'',
            'fallback'=>'Media presentation.',
        ],'settings'=>[],'responsive'=>[]]]]]]]];

        $normalised=app(PageDocumentService::class)->normalise($document);

        $content=$normalised['sections'][0]['columns'][0]['elements'][0]['content'];
        $this->assertStringContainsString('https://cdn.example.com/message.mp4', $content['html']);
        $this->assertStringContainsString('/media/019abcde-1234-7000-8000-abcdefabcdef', $content['html']);
        $this->assertStringContainsString('https://images.example.com/hero.jpg', $content['css']);
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
