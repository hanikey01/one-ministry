<?php

namespace Tests\Unit;

use App\Services\Ai\AiCanvasSectionService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiCanvasSectionServiceTest extends TestCase
{
    public function test_it_compiles_independent_html_css_sections_into_the_page_document(): void
    {
        $result = app(AiCanvasSectionService::class)->compile([
            'title' => 'Plan a visit', 'seo_title' => 'Plan a visit', 'seo_description' => 'Know what to expect.',
            'sections' => [$this->section()],
        ], []);

        $element = $result['document']['sections'][0]['columns'][0]['elements'][0];
        $this->assertSame('custom_code', $element['type']);
        $this->assertStringContainsString('<section', $element['content']['html']);
        $this->assertStringContainsString('@media', $element['content']['css']);
        $this->assertSame('', $element['content']['javascript']);
    }

    public function test_it_rejects_executable_or_remote_section_content(): void
    {
        $section = $this->section();
        $section['html'] = '<section><script>alert(1)</script></section>';
        $this->expectException(ValidationException::class);
        app(AiCanvasSectionService::class)->normaliseSection($section);
    }

    public function test_it_rejects_a_canvas_composition_above_the_editor_budget(): void
    {
        $section = $this->section();
        $section['html'] = '<section><p data-ai-editable="body" data-ai-label="Body">'.str_repeat('x', 49900).'</p></section>';
        $section['css'] .= str_repeat(' ', 49000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AiCanvasSectionService::class)->compile([
            'title' => 'Oversized page',
            'seo_title' => '',
            'seo_description' => '',
            'sections' => array_fill(0, 7, $section),
        ], []);
    }

    public function test_it_rejects_missing_or_non_responsive_css(): void
    {
        $section = $this->section();
        $section['css'] = '';
        try {
            app(AiCanvasSectionService::class)->normaliseSection($section);
            $this->fail('Empty CSS should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $section = $this->section();
        $section['css'] = '.hero{display:grid;padding:3rem;background:var(--surface);color:var(--ink)}';
        $this->expectException(ValidationException::class);
        app(AiCanvasSectionService::class)->normaliseSection($section);
    }

    public function test_the_provider_contract_is_small_and_does_not_expose_native_elements(): void
    {
        $schema = json_encode(AiCanvasSectionService::schema(), JSON_THROW_ON_ERROR);
        $this->assertLessThan(3000, strlen($schema));
        $this->assertStringNotContainsString('element_types', $schema);
        $this->assertStringContainsString('html', $schema);
        $this->assertStringContainsString('css', $schema);
    }

    public function test_the_batch_contract_requests_an_ordered_section_list(): void
    {
        $schema = AiCanvasSectionService::batchSchema();

        $this->assertSame('array', $schema['properties']['sections']['type']);
        $this->assertSame(AiCanvasSectionService::schema()['properties']['section'], $schema['properties']['sections']['items']);
        $this->assertSame(['sections'], $schema['required']);
    }

    private function section(): array
    {
        return [
            'name' => 'Welcome hero', 'purpose' => 'Welcome first-time visitors.',
            'html' => '<section class="hero"><div><p data-ai-editable="hero-eyebrow" data-ai-label="Hero eyebrow">WELCOME</p><h1 data-ai-editable="hero-heading" data-ai-label="Hero heading">You belong here</h1><p data-ai-editable="hero-introduction" data-ai-label="Hero introduction">Plan your first visit.</p><img data-ai-media="hero-image" data-ai-media-type="image" data-ai-label="Hero image" alt=""></div></section>',
            'css' => '.hero{display:grid;min-height:28rem;padding:clamp(2rem,7vw,6rem);background:var(--surface);color:var(--ink)}@media(max-width:640px){.hero{min-height:22rem;padding:2rem 1rem}}',
            'javascript' => '', 'fallback' => 'Welcome. Plan your first visit.',
        ];
    }

    public function test_it_rejects_duplicate_editable_markers_and_invented_media_sources(): void
    {
        $section = $this->section();
        $section['html'] = '<section><h2 data-ai-editable="heading">One</h2><p data-ai-editable="heading">Two</p></section>';
        try {
            app(AiCanvasSectionService::class)->normaliseSection($section);
            $this->fail('Duplicate markers should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $section = $this->section();
        $section['html'] = str_replace('alt=""', 'src="https://example.com/photo.jpg" alt=""', $section['html']);
        $this->expectException(ValidationException::class);
        app(AiCanvasSectionService::class)->normaliseSection($section);
    }

    public function test_it_accepts_only_explicitly_approved_internal_media_paths(): void
    {
        $section = $this->section();
        $section['html'] = str_replace('alt=""', 'src="/media/approved-image" alt=""', $section['html']);
        $result = app(AiCanvasSectionService::class)->normaliseSection($section, ['/media/approved-image']);
        $this->assertStringContainsString('/media/approved-image', $result['html']);

        $section['html'] = str_replace('/media/approved-image', '/media/invented-image', $section['html']);
        $this->expectException(ValidationException::class);
        app(AiCanvasSectionService::class)->normaliseSection($section, ['/media/approved-image']);
    }

    public function test_it_neutralises_invented_destinations_for_staff_editing(): void
    {
        $section = $this->section();
        $section['html'] = str_replace('</section>', '<a href="/invented-page">Read more</a></section>', $section['html']);

        $result = app(AiCanvasSectionService::class)->normaliseSection($section);

        $this->assertStringContainsString('<a href="#">Read more</a>', $result['html']);
        $this->assertStringNotContainsString('/invented-page', $result['html']);
    }
}
