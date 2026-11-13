<?php

namespace Tests\Unit;

use App\Services\Ai\AiElementCatalogue;
use App\Services\Ai\AiPageCompositionCompiler;
use App\Services\Ai\AiPageCompositionContract;
use App\Services\HomepageStarterService;
use App\Services\PageElementRegistry;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiPageCompositionCompilerTest extends TestCase
{
    public static function composition(): array
    {
        $element = ['type' => 'heading', 'content' => [['field' => 'text', 'value' => 'Welcome']], 'design' => [], 'responsive' => [], 'media' => []];

        return ['title' => 'Welcome', 'seo_title' => 'Welcome', 'seo_description' => 'A welcome page.', 'sections' => [['purpose' => 'Welcome', 'design' => [], 'responsive' => [], 'media' => [], 'columns' => [['weight' => 1, 'design' => [], 'responsive' => [], 'media' => [], 'elements' => [$element]]]]]];
    }

    public function test_compiler_generates_ids_defaults_and_responsive_layout(): void
    {
        $composition = self::composition();
        $composition['sections'][0]['columns'][] = $composition['sections'][0]['columns'][0];
        $result = app(AiPageCompositionCompiler::class)->compile($composition, []);
        $this->assertSame(2, $result['document']['version']);
        $columns = $result['document']['sections'][0]['columns'];
        $this->assertSame('50%', $columns[0]['responsive']['desktop']['custom_width']);
        $this->assertSame('full', $columns[0]['responsive']['mobile']['width']);
        $this->assertNotSame($columns[0]['id'], $columns[1]['id']);
        $this->assertSame('Welcome', $columns[0]['elements'][0]['content']['text']);
    }

    public static function invalidCases(): array
    {
        return array_map(fn ($case) => [$case], ['unknown', 'unsafe_link', 'media', 'columns', 'sections', 'elements', 'total', 'css', 'duplicate_field', 'unknown_field', 'uuid']);
    }

    #[DataProvider('invalidCases')]
    public function test_compiler_rejects_unsafe_or_excessive_compositions(string $case): void
    {
        $composition = self::composition();
        $element = &$composition['sections'][0]['columns'][0]['elements'][0];
        match ($case) {
            'unknown' => $element['type'] = 'unregistered',
            'unsafe_link' => $element = ['type' => 'button', 'content' => [['field' => 'url', 'value' => '//evil.example'], ['field' => 'label', 'value' => 'Go']]],
            'media' => $element['media'] = [['field' => 'url', 'id' => 'private-image']],
            'columns' => $composition['sections'][0]['columns'] = array_fill(0, 5, $composition['sections'][0]['columns'][0]),
            'sections' => $composition['sections'] = array_fill(0, 13, $composition['sections'][0]),
            'elements' => $composition['sections'][0]['columns'][0]['elements'] = array_fill(0, 9, $element),
            'total' => $composition['sections'] = array_fill(0, 6, array_replace_recursive($composition['sections'][0], ['columns' => [['elements' => array_fill(0, 8, $element)]]])),
            'css' => $element['design'] = [['field' => 'custom_css', 'value' => 'body { display:none }']],
            'duplicate_field' => $element['content'][] = $element['content'][0],
            'unknown_field' => $element['content'][] = ['field' => 'secret', 'value' => 'x'],
            'uuid' => $element['id'] = 'model-generated',
        };
        unset($element);
        $this->expectException(ValidationException::class);
        app(AiPageCompositionCompiler::class)->compile($composition, []);
    }

    public function test_catalogue_and_schema_do_not_include_editor_internals(): void
    {
        $registry = app(PageElementRegistry::class);
        $catalogue = app(AiElementCatalogue::class)->forFeatures([]);
        $this->assertArrayNotHasKey('giving', $catalogue);
        foreach ($catalogue as $item) {
            foreach (['defaults', 'renderer', 'inspector', 'setting_definitions', 'responsive_definitions'] as $key) {
                $this->assertArrayNotHasKey($key, $item);
            }
        }
        $this->assertLessThan(strlen(json_encode($registry->available([]))) / 3, strlen(json_encode($catalogue)));
        $schema = json_encode(AiPageCompositionContract::schema(array_keys($catalogue)));
        $this->assertLessThan(20000, strlen($schema));
        $this->assertStringNotContainsString('uuid', $schema);
    }

    public function test_homepage_is_a_complete_native_version_two_document(): void
    {
        $document = app(HomepageStarterService::class)->document([]);
        $this->assertSame(2, $document['version']);
        $this->assertCount(5, $document['sections']);
        $this->assertCount(2, $document['sections'][0]['columns']);
        $this->assertSame('hero', $document['sections'][0]['columns'][0]['elements'][0]['type']);
    }
}
