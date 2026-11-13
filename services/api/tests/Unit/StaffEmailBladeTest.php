<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StaffEmailBladeTest extends TestCase
{
    public function test_staff_email_view_compiles_to_valid_php(): void
    {
        $source = file_get_contents(resource_path('views/staff/email.blade.php'));

        $this->assertIsString($source);

        $compiled = Blade::compileString($source);

        token_get_all($compiled, TOKEN_PARSE);

        $this->assertNotSame('', trim($compiled));
    }
}
