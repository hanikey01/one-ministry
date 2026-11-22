<?php

namespace Tests\Unit;

use App\Support\AddressCatalog;
use PHPUnit\Framework\TestCase;

class AddressCatalogTest extends TestCase
{
    public function test_country_and_subdivision_codes_resolve_to_stable_names(): void
    {
        $catalog = new AddressCatalog();

        $this->assertSame('Nigeria', $catalog->countryName('NG'));
        $this->assertSame('Lagos', $catalog->subdivisionName('NG', 'LA'));
        $this->assertArrayHasKey('US', $catalog->countries());
    }

    public function test_catalog_is_deployable_without_a_runtime_composer_package(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/AddressCatalog.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString('CommerceGuys\\', $source);
        $this->assertFileExists(dirname(__DIR__, 2).'/resources/addressing/countries.en.json');
        $this->assertFileExists(dirname(__DIR__, 2).'/resources/addressing/subdivisions/NG.json');
    }

    public function test_structured_address_produces_the_compatibility_display_address(): void
    {
        $catalog = new AddressCatalog();

        $this->assertSame("12 Unity Avenue\nCommunity Hall\nLagos, Lagos, 100001\nNigeria", $catalog->format([
            'address_line_1' => '12 Unity Avenue',
            'address_line_2' => 'Community Hall',
            'locality' => 'Lagos',
            'region_name' => 'Lagos',
            'postal_code' => '100001',
            'country_name' => 'Nigeria',
        ]));
    }
}
