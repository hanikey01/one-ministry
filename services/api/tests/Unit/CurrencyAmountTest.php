<?php

namespace Tests\Unit;

use App\Services\CurrencyAmount;
use PHPUnit\Framework\TestCase;

class CurrencyAmountTest extends TestCase
{
    public function test_currency_exponents_are_not_assumed_to_be_two(): void
    {
        $money=new CurrencyAmount();
        $this->assertSame('2500',$money->major(2500,'JPY'));
        $this->assertSame('25.00',$money->major(2500,'USD'));
        $this->assertSame('2.500',$money->major(2500,'KWD'));
        $this->assertSame(2500,$money->minor('2.500','KWD'));
    }
}
