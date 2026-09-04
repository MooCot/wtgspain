<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Persistence\Eloquent\Models\Money;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferPriceCastTest extends TestCase
{
    use RefreshDatabase;

    public function testPriceCastsToMoneyValueObject(): void
    {
        $offer = Offer::factory()->create(['price' => 72500, 'currency' => 'EUR']);

        $this->assertInstanceOf(Money::class, $offer->price);
        $this->assertSame(72500, $offer->price->amount);
        $this->assertSame('EUR', $offer->price->currency);
    }

    public function testPriceAcceptsRawIntOnWrite(): void
    {
        $offer = Offer::factory()->create(['price' => 50000, 'currency' => 'USD']);

        $fresh = $offer->fresh();
        $this->assertSame(50000, $fresh->price->amount);
        $this->assertSame('USD', $fresh->price->currency);
    }

    public function testPriceAcceptsMoneyInstanceOnWrite(): void
    {
        $offer = Offer::factory()->create(['price' => 1, 'currency' => 'EUR']);

        $offer->price = new Money(99900, 'GBP');
        $offer->save();

        $fresh = $offer->fresh();
        $this->assertSame(99900, $fresh->price->amount);
        $this->assertSame('GBP', $fresh->price->currency);
    }
}
