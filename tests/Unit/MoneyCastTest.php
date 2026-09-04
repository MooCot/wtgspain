<?php

namespace Tests\Unit;

use App\Infrastructure\Persistence\Eloquent\Models\Casts\MoneyCast;
use App\Infrastructure\Persistence\Eloquent\Models\Money;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use PHPUnit\Framework\TestCase;

class MoneyCastTest extends TestCase
{
    public function testGetCombinesPriceAndCurrencyIntoMoney(): void
    {
        $cast = new MoneyCast;

        $money = $cast->get(new Offer, 'price', null, ['price' => '72500', 'currency' => 'EUR']);

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame(72500, $money->amount);
        $this->assertSame('EUR', $money->currency);
    }

    public function testGetReturnsNullWhenPriceOrCurrencyMissing(): void
    {
        $cast = new MoneyCast;

        $this->assertNull($cast->get(new Offer, 'price', null, ['price' => 72500]));
        $this->assertNull($cast->get(new Offer, 'price', null, ['currency' => 'EUR']));
    }

    public function testSetAcceptsRawIntValue(): void
    {
        $cast = new MoneyCast;

        $attributes = $cast->set(new Offer, 'price', 50000, []);

        $this->assertSame(['price' => 50000], $attributes);
    }

    public function testSetAcceptsMoneyInstance(): void
    {
        $cast = new MoneyCast;

        $attributes = $cast->set(new Offer, 'price', new Money(99900, 'GBP'), []);

        $this->assertSame(['price' => 99900, 'currency' => 'GBP'], $attributes);
    }
}
