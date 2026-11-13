<?php

namespace Tests\Unit;

use App\Services\Ai\AiCompositionProbe;
use App\Services\Ai\AiProviderException;
use Tests\TestCase;

class AiCompositionProbeTest extends TestCase
{
    public static function compositionFixture(): array
    {
        $composition = AiPageCompositionCompilerTest::composition();
        $composition['ready'] = true;
        $composition['sections'][] = $composition['sections'][0];
        return $composition;
    }

    public function test_representative_nested_composition_passes_with_a_bounded_schema(): void
    {
        AiCompositionProbe::validate(self::compositionFixture());
        $this->assertLessThan(20000, strlen(json_encode(AiCompositionProbe::schema())));
    }

    public function test_tiny_or_invalid_composition_cannot_verify_a_model(): void
    {
        $this->expectException(AiProviderException::class);
        AiCompositionProbe::validate(['ready' => true, 'sections' => []]);
    }

    public function test_invalid_nested_element_cannot_verify_a_model(): void
    {
        $output = self::compositionFixture();
        $output['sections'][0]['columns'][0]['elements'][0]['type'] = 'unknown';
        $this->expectException(AiProviderException::class);
        AiCompositionProbe::validate($output);
    }
}
