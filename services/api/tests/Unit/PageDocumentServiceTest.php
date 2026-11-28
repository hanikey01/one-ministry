<?php

namespace Tests\Unit;

use App\Services\AboutPageStarterService;
use App\Services\CrusadeArchiveStarterService;
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
                        'slide_one_type' => 'flyer', 'slide_one_presentation' => 'flyer', 'slide_one_media_url' => 'https://cdn.example.test/flyer.jpg', 'slide_one_title' => 'Welcome', 'slide_one_body' => 'A welcome flyer', 'slide_one_button_label' => 'Plan a visit', 'slide_one_button_url' => '/visit',
                        'slide_two_type' => 'video_upload', 'slide_two_presentation' => 'editorial', 'slide_two_media_url' => 'https://cdn.example.test/worship.mp4', 'slide_two_title' => 'Worship', 'slide_two_body' => 'An uploaded video', 'slide_two_button_label' => 'Watch', 'slide_two_button_url' => '/worship',
                        'slide_three_type' => 'video_link', 'slide_three_presentation' => 'editorial', 'slide_three_media_url' => 'https://www.youtube.com/watch?v=6CKCThJB5w0', 'slide_three_title' => 'Message', 'slide_three_body' => 'An external video', 'slide_three_button_label' => 'Explore', 'slide_three_button_url' => '/sermons',
                    ],
                ]],
            ]],
        ]]];

        $carousel = app(PageDocumentService::class)->normalise($document)['sections'][0]['columns'][0]['elements'][0]['content'];

        $this->assertSame('flyer', $carousel['slide_one_presentation']);
        $this->assertSame('video_upload', $carousel['slide_two_type']);
        $this->assertSame('video_link', $carousel['slide_three_type']);
        $this->assertSame('/sermons', $carousel['slide_three_button_url']);
    }

    public function test_homepage_starter_begins_with_a_theme_aware_three_slide_carousel(): void
    {
        $document = app(HomepageStarterService::class)->document([]);
        $lead = $document['sections'][0]['columns'][0]['elements'][0];

        $this->assertSame('carousel', $lead['type']);
        $this->assertSame('/images/pages/home-arrival-hero.png', $lead['content']['slide_one_media_url']);
        $this->assertSame('/images/pages/home-community-carousel.png', $lead['content']['slide_two_media_url']);
        $this->assertSame('/images/pages/home-service-carousel.png', $lead['content']['slide_three_media_url']);
        $this->assertSame('/service-times', $lead['content']['slide_one_button_url']);
        $this->assertSame('/about-us', $lead['content']['slide_three_button_url']);
        $this->assertSame('', $lead['content']['slide_one_body']);
        $this->assertSame('', $lead['content']['slide_two_body']);
        $this->assertSame('', $lead['content']['slide_three_body']);
        $this->assertSame('editorial', $lead['content']['slide_one_presentation']);
        $this->assertSame('editorial', $lead['content']['slide_two_presentation']);
        $this->assertSame('editorial', $lead['content']['slide_three_presentation']);
        $this->assertStringContainsString('public-page-carousel', $lead['settings']['custom_css']);
        $this->assertStringContainsString('touch-action: pan-y', $lead['settings']['custom_css']);
        $this->assertStringContainsString('border: 2px solid #ffffff !important', $lead['settings']['custom_css']);
        $this->assertStringContainsString('color: #ffffff !important', $lead['settings']['custom_css']);
        $this->assertStringContainsString('background: #ffffff !important; color: var(--brand) !important', $lead['settings']['custom_css']);
        $this->assertStringContainsString('border-radius: 999px !important', $lead['settings']['custom_css']);
        $this->assertStringContainsString('public-page-carousel-slide--editorial', $lead['settings']['custom_css']);
        $this->assertStringContainsString('min-height: clamp(380px, 46vw, 540px)', $lead['settings']['custom_css']);
        $this->assertStringContainsString('min-height: 430px', $lead['settings']['custom_css']);
        $this->assertStringContainsString('justify-content: end; align-items: center; text-align: center', $lead['settings']['custom_css']);
        $this->assertStringContainsString('.public-page-carousel-overlay p { display: none; }', $lead['settings']['custom_css']);
        $this->assertStringContainsString('.public-page-carousel-overlay .button { align-self: center', $lead['settings']['custom_css']);
    }

    public function test_carousel_flyer_presentation_keeps_media_separate_from_copy(): void
    {
        $renderer = file_get_contents(resource_path('views/public/partials/page-element.blade.php'));
        $styles = file_get_contents(resource_path('css/app.css'));
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('public-page-carousel-slide--flyer', $renderer);
        $this->assertStringContainsString("data-slide-presentation=\"{{ \$isFlyerPresentation ? 'flyer' : 'editorial' }}\"", $renderer);
        $this->assertStringContainsString('object-fit:contain', $styles);
        $this->assertStringContainsString('.public-page-carousel-slide--flyer .public-page-carousel-overlay', $styles);
        $this->assertStringContainsString('data-carousel-previous', $renderer);
        $this->assertStringContainsString("addPageListener(carousel, 'pointerdown'", $script);
        $this->assertStringNotContainsString('}, 6000);', $script);
    }

    public function test_carousel_image_slide_initialises_video_flags_before_rendering_copy(): void
    {
        $rendered = view('public.partials.page-element', [
            'element' => [
                'id' => 'flyer-carousel',
                'type' => 'carousel',
                'content' => [
                    'slide_one_type' => 'flyer',
                    'slide_one_presentation' => 'flyer',
                    'slide_one_media_url' => '/images/program-flyer.jpg',
                    'slide_one_title' => 'Upcoming programme',
                    'slide_one_body' => 'Join us for the next gathering.',
                    'slide_one_button_label' => '',
                    'slide_one_button_url' => '',
                ],
                'settings' => [],
                'responsive' => [],
            ],
            'features' => [],
            'pageElementData' => [],
        ])->render();

        $this->assertStringContainsString('public-page-carousel-slide--flyer', $rendered);
        $this->assertStringContainsString('/images/program-flyer.jpg', $rendered);
        $this->assertStringContainsString('Upcoming programme', $rendered);
        $this->assertStringNotContainsString('>WATCH<', $rendered);
    }

    public function test_editorial_carousel_slide_omits_featured_label_and_supporting_copy(): void
    {
        $rendered = view('public.partials.page-element', [
            'element' => [
                'id' => 'editorial-carousel',
                'type' => 'carousel',
                'content' => [
                    'slide_one_type' => 'flyer',
                    'slide_one_presentation' => 'editorial',
                    'slide_one_media_url' => '/images/community.jpg',
                    'slide_one_title' => 'Faith expressed through care.',
                    'slide_one_body' => '',
                    'slide_one_button_label' => 'About us',
                    'slide_one_button_url' => '/about-us',
                ],
                'settings' => [],
                'responsive' => [],
            ],
            'features' => [],
            'pageElementData' => [],
        ])->render();

        $this->assertStringContainsString('Faith expressed through care.', $rendered);
        $this->assertStringContainsString('About us', $rendered);
        $this->assertStringNotContainsString('FEATURED', $rendered);
        $this->assertStringNotContainsString('<p>', $rendered);
    }

    public function test_homepage_starter_keeps_connection_pathways_unique_and_points_its_story_to_about(): void
    {
        $document = app(HomepageStarterService::class)->document([]);
        $typesBySection = collect($document['sections'])->map(fn (array $section) => collect($section['columns'])
            ->flatMap(fn (array $column) => collect($column['elements'])->pluck('type'))
            ->all());
        $pathwayUrls = collect($document['sections'][2]['columns'])
            ->map(fn (array $column) => $column['elements'][0]['content']['button_url'])
            ->all();
        $story = $document['sections'][4]['columns'][0]['elements'][0];

        $this->assertSame(['events', 'locations'], $typesBySection[3]);
        $this->assertSame(['/member/prayer', '/testimonies', '/departments', '/contact'], $pathwayUrls);
        $this->assertSame('Submit a prayer request', $document['sections'][2]['columns'][0]['elements'][0]['content']['title']);
        $this->assertNotContains('departments', $typesBySection[3]);
        $this->assertNotContains('prayer', collect($typesBySection)->flatten()->all());
        $this->assertSame('About us', $story['content']['button_label']);
        $this->assertSame('/about-us', $story['content']['button_url']);
    }

    public function test_homepage_starter_only_includes_giving_when_the_module_is_enabled(): void
    {
        $withoutGiving = app(HomepageStarterService::class)->document([]);
        $withGiving = app(HomepageStarterService::class)->document(['giving']);
        $withoutUrls = collect($withoutGiving['sections'])->flatMap(fn (array $section) => collect($section['columns'])->flatMap(fn (array $column) => collect($column['elements'])->pluck('content.button_url')));
        $withUrls = collect($withGiving['sections'])->flatMap(fn (array $section) => collect($section['columns'])->flatMap(fn (array $column) => collect($column['elements'])->pluck('content.button_url')));

        $this->assertNotContains('/give', $withoutUrls);
        $this->assertContains('/give', $withUrls);
        $this->assertCount(5, $withoutGiving['sections']);
        $this->assertCount(6, $withGiving['sections']);
        $givingSection = $withGiving['sections'][5];
        $this->assertCount(1, $givingSection['columns']);
        $this->assertSame('media_text', $givingSection['columns'][0]['elements'][0]['type']);
        $this->assertSame('/images/pages/giving-community-support.png', $givingSection['columns'][0]['elements'][0]['content']['media_url']);
        $this->assertSame('right', $givingSection['columns'][0]['elements'][0]['content']['placement']);
        $this->assertStringContainsString('grid-template-columns: minmax(280px, .85fr) minmax(0, 1.15fr)', $givingSection['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertStringContainsString('.public-page-media-visual { order: 2; aspect-ratio: 16 / 9', $givingSection['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertStringContainsString('height: 100%; min-height: 0; object-fit: cover', $givingSection['columns'][0]['elements'][0]['settings']['custom_css']);
    }

    public function test_about_page_starter_is_a_theme_aware_native_editable_document(): void
    {
        $document = app(AboutPageStarterService::class)->document([]);
        $types = collect($document['sections'])->flatMap(fn (array $section) => collect($section['columns'])->flatMap(fn (array $column) => collect($column['elements'])->pluck('type')));

        $this->assertSame(2, $document['version']);
        $this->assertCount(6, $document['sections']);
        $this->assertCount(1, $document['sections'][0]['columns']);
        $this->assertSame('hero', $document['sections'][0]['columns'][0]['elements'][0]['type']);
        $this->assertSame('/service-times', $document['sections'][0]['columns'][0]['elements'][0]['content']['button_url']);
        $this->assertSame('Discover the story, values, and people that shape this ministry.', $document['sections'][0]['columns'][0]['elements'][0]['content']['body']);
        $this->assertSame('auto', $document['sections'][0]['columns'][0]['elements'][0]['settings']['button_width']);
        $this->assertStringContainsString('align-content: end', $document['sections'][0]['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertStringContainsString('min-height: 500px', $document['sections'][0]['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertStringContainsString('font-size: clamp(2.2rem, 3.4vw, 3.35rem)', $document['sections'][0]['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertStringContainsString('linear-gradient(90deg', $document['sections'][0]['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertSame('/images/pages/home-arrival-hero.png', $document['sections'][0]['settings']['background_image']);
        $this->assertSame('right_center', $document['sections'][0]['settings']['background_position']);
        $this->assertSame('cover', $document['sections'][0]['settings']['background_size']);
        $this->assertSame('full', $document['sections'][0]['settings']['content_width']);
        $this->assertSame(['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px'], $document['sections'][0]['responsive']['mobile']['padding']);
        $this->assertSame(['image_card', 'image_card'], collect($document['sections'][5]['columns'])->map(fn (array $column) => $column['elements'][0]['type'])->all());
        $this->assertSame(['/departments', '/locations'], collect($document['sections'][5]['columns'])->map(fn (array $column) => $column['elements'][0]['content']['button_url'])->all());
        $this->assertSame(['/images/pages/home-service-carousel.png', '/images/pages/home-arrival-hero.png'], collect($document['sections'][5]['columns'])->map(fn (array $column) => $column['elements'][0]['content']['url'])->all());
        $this->assertNotContains('departments', $types);
        $this->assertNotContains('locations', $types);
        $this->assertNotContains('testimonial', $types);
        $this->assertNotContains('statistics', $types);
    }

    public function test_crusade_archive_starter_has_empty_native_media_slots_for_staff_content(): void
    {
        $document = app(CrusadeArchiveStarterService::class)->document([]);
        $elements = collect($document['sections'])->flatMap(fn (array $section) => collect($section['columns'])->flatMap(fn (array $column) => $column['elements']));
        $videos = $elements->where('type', 'video_embed')->values();

        $this->assertSame(2, $document['version']);
        $this->assertCount(4, $document['sections']);
        $this->assertSame('video_embed', $document['sections'][0]['columns'][0]['elements'][0]['type']);
        $this->assertSame('standard', $document['sections'][0]['columns'][0]['elements'][0]['content']['presentation']);
        $this->assertSame('Full crusade recording', $document['sections'][0]['columns'][0]['elements'][0]['content']['title']);
        $this->assertSame('full', $document['sections'][0]['settings']['content_width']);
        $this->assertStringContainsString('width: 100% !important', $document['sections'][0]['columns'][0]['elements'][0]['settings']['custom_css']);
        $this->assertSame('gallery', $document['sections'][2]['columns'][0]['elements'][0]['type']);
        $this->assertSame('', $document['sections'][2]['columns'][0]['elements'][0]['content']['images']);
        $this->assertSame('carousel', $document['sections'][2]['columns'][0]['elements'][0]['content']['display']);
        $this->assertCount(4, $videos);
        $this->assertSame(['Full crusade recording', 'Highlight one', 'Highlight two', 'Highlight three'], $videos->pluck('content.title')->all());
        $this->assertSame(['', '', '', ''], $videos->pluck('content.url')->all());
        $this->assertStringNotContainsString('Watch the full crusade', json_encode($document, JSON_THROW_ON_ERROR));
    }

    public function test_structured_elements_and_editor_wrappers_stretch_across_their_column(): void
    {
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.public-page-structured-column > :is(.public-page-element, [data-page-editor-object="element"]) { width: 100%; max-width: 100%; justify-self: stretch; }', $styles);
        $this->assertStringContainsString('.public-page-structured-column > [data-page-editor-object="element"] > .public-page-element', $styles);
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

    public function test_native_media_elements_accept_internal_and_external_http_media_urls(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-media', 'type' => 'section', 'settings' => [], 'responsive' => [],
            'columns' => [[
                'id' => 'column-media', 'settings' => [], 'responsive' => [],
                'elements' => [
                    ['id' => 'image', 'type' => 'image', 'content' => ['url' => '/media/8c2738b3-8de1-46c6-987b-f51f51649435', 'alt' => 'Internal image', 'caption' => '', 'image_hint' => ''], 'settings' => [], 'responsive' => []],
                    ['id' => 'media-text', 'type' => 'media_text', 'content' => ['media_url' => 'https://images.example.test/welcome.jpg', 'media_alt' => 'External image', 'placement' => 'left', 'title' => 'Welcome', 'body' => 'Hello', 'button_label' => '', 'button_url' => ''], 'settings' => [], 'responsive' => []],
                    ['id' => 'video', 'type' => 'video_embed', 'content' => ['url' => 'https://media.example.test/message.mp4', 'title' => 'Message', 'caption' => 'Watch'], 'settings' => [], 'responsive' => []],
                    ['id' => 'gallery', 'type' => 'gallery', 'content' => ['images' => "/media/8c2738b3-8de1-46c6-987b-f51f51649435\nhttps://images.example.test/two.jpg", 'columns' => 'two', 'caption' => 'Gallery'], 'settings' => [], 'responsive' => []],
                ],
            ]],
        ]]];

        $normalised = app(PageDocumentService::class)->normalise($document);
        $elements = $normalised['sections'][0]['columns'][0]['elements'];

        $this->assertSame('/media/8c2738b3-8de1-46c6-987b-f51f51649435', $elements[0]['content']['url']);
        $this->assertSame('https://images.example.test/welcome.jpg', $elements[1]['content']['media_url']);
        $this->assertSame('https://media.example.test/message.mp4', $elements[2]['content']['url']);
        $this->assertStringContainsString('/media/', $elements[3]['content']['images']);
    }

    public function test_native_media_elements_reject_unsafe_url_schemes(): void
    {
        $document = ['version' => 2, 'sections' => [[
            'id' => 'section-media', 'type' => 'section', 'settings' => [], 'responsive' => [],
            'columns' => [['id' => 'column-media', 'settings' => [], 'responsive' => [], 'elements' => [[
                'id' => 'image', 'type' => 'image', 'content' => ['url' => 'javascript:alert(1)', 'alt' => 'Unsafe', 'caption' => '', 'image_hint' => ''], 'settings' => [], 'responsive' => [],
            ]]]],
        ]]];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PageDocumentService::class)->normalise($document);
    }

    public function test_custom_canvas_renderer_preserves_authored_grid_or_flex_layout(): void
    {
        $rendered = view('public.partials.page-element', ['element' => [
            'id' => 'canvas-layout',
            'type' => 'custom_code',
            'settings' => ['custom_class' => 'ai-canvas-design'],
            'content' => [
                'reason' => 'A composed visual section.',
                'fallback' => 'A composed visual section.',
                'html' => '<section class="canvas-layout"><h2>Welcome</h2><a href="#">Explore</a></section>',
                'css' => '.canvas-layout{display:grid;grid-template-columns:1fr 1fr}',
                'javascript' => '',
            ],
        ]])->render();

        $this->assertStringContainsString('display:grid', $rendered);
        $this->assertStringContainsString('body&gt;:not(script){width:100%!important', $rendered);
        $this->assertStringContainsString('inline-size:100%!important', $rendered);
        $this->assertStringContainsString('flex:1 1 100%!important', $rendered);
        $this->assertStringNotContainsString('body&gt;:not(script){display:block!important', $rendered);
        $this->assertStringContainsString('data-ai-canvas-design', $rendered);
        $this->assertStringContainsString('aria-disabled=&quot;true&quot;', $rendered);
        $this->assertStringNotContainsString('href=&quot;#&quot;', $rendered);
        $this->assertStringNotContainsString('allow-top-navigation-by-user-activation', $rendered);
    }
}
