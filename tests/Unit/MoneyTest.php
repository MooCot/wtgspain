<?php

namespace Tests\Unit;

use App\Infrastructure\Persistence\Eloquent\Models\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testEqualsReturnsTrueForSameAmountAndCurrency(): void
    {
        $a = new Money(72500, 'EUR');
        $b = new Money(72500, 'EUR');

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentAmount(): void
    {
        $a = new Money(72500, 'EUR');
        $b = new Money(65000, 'EUR');

        $this->assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCurrency(): void
    {
        $a = new Money(72500, 'EUR');
        $b = new Money(72500, 'USD');

        $this->assertFalse($a->equals($b));
    }
}
