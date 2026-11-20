<?php

namespace Tests\Unit;

use App\Services\Ai\AiCompositionProbe;
use App\Services\Ai\AiProviderException;
use Tests\TestCase;

class AiCompositionProbeTest extends TestCase
{
    public static function compositionFixture(): array
    {
        return ['section' => [
            'name' => 'Welcome', 'purpose' => 'Welcome visitors',
            'html' => '<section class="welcome"><h1 data-ai-editable="welcome-heading" data-ai-label="Welcome heading">Welcome</h1><p data-ai-editable="welcome-introduction" data-ai-label="Welcome introduction">A welcoming introduction.</p></section>',
            'css' => '.welcome{display:grid;padding:3rem;background:var(--surface);color:var(--ink)}@media(max-width:640px){.welcome{padding:1.5rem}}',
            'javascript' => '', 'fallback' => 'Welcome. A welcoming introduction.',
        ]];
    }

    public function test_representative_nested_composition_passes_with_a_bounded_schema(): void
    {
        AiCompositionProbe::validate(self::compositionFixture());
        $this->assertLessThan(20000, strlen(json_encode(AiCompositionProbe::schema())));
    }

    public function test_tiny_or_invalid_composition_cannot_verify_a_model(): void
    {
        $this->expectException(AiProviderException::class);
        AiCompositionProbe::validate(['section' => []]);
    }

    public function test_invalid_nested_element_cannot_verify_a_model(): void
    {
        $output = self::compositionFixture();
        $output['section']['html'] = '<section><script>alert(1)</script></section>';
        $this->expectException(AiProviderException::class);
        AiCompositionProbe::validate($output);
    }
}
