<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SetupWizardContractTest extends TestCase
{
    public function test_address_fields_follow_their_dependency_order(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/setup/wizard.blade.php');

        $country = strpos($view, 'name="location_country_code"');
        $region = strpos($view, 'name="location_region_code"');
        $city = strpos($view, 'name="location_city"');
        $street = strpos($view, 'name="location_address_line_1"');
        $timezone = strpos($view, '<x-ui.timezone-select');

        $this->assertIsInt($country);
        $this->assertGreaterThan($country, $region);
        $this->assertGreaterThan($region, $city);
        $this->assertGreaterThan($city, $street);
        $this->assertGreaterThan($street, $timezone);
    }

    public function test_custom_colour_is_collected_validated_and_persisted(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/setup/wizard.blade.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/SetupWizardController.php');
        $installer = file_get_contents(dirname(__DIR__, 2).'/app/Actions/InstallMinistry.php');

        $this->assertStringContainsString('name="chrome_mode" value="custom"', $view);
        $this->assertStringContainsString('name="chrome_color"', $view);
        $this->assertStringContainsString("'required_if:chrome_mode,custom'", $controller);
        $this->assertStringContainsString("'chrome_mode', 'chrome_color'", $controller);
        $this->assertStringContainsString("'chrome_mode' => \$input['chrome_mode'] ?? 'preset'", $installer);
        $this->assertStringContainsString("strtoupper((string) \$input['chrome_color'])", $installer);
    }
}
