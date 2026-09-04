<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Imports\Ports\OfferRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function offerAttributes(array $overrides = []): array
    {
        return collect([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => '2026-09-10 23:59:59',
        ])->merge($overrides)->all();
    }

    public function testItCreatesOfferWhenNotExists(): void
    {
        $repository = $this->app->make(OfferRepository::class);
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        $offer = $repository->updateOrCreate($supplier, $property, 'offer-1', $this->offerAttributes());

        $this->assertDatabaseCount('offers', 1);
        $this->assertSame($supplier->id, $offer->supplier_id);
        $this->assertSame('offer-1', $offer->external_id);
        $this->assertSame(2, $offer->available_units);
    }

    public function testItUpdatesExistingOfferInsteadOfDuplicating(): void
    {
        $repository = $this->app->make(OfferRepository::class);
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        $repository->updateOrCreate($supplier, $property, 'offer-1', $this->offerAttributes());

        $updated = $repository->updateOrCreate($supplier, $property, 'offer-1', $this->offerAttributes([
            'price' => 65000,
            'available_units' => 5,
        ]));

        $this->assertDatabaseCount('offers', 1);
        $this->assertSame(65000, $updated->price);
        $this->assertSame(5, $updated->available_units);
    }

    public function testItAtomicallyDecrementsAvailableUnits(): void
    {
        $repository = $this->app->make(OfferRepository::class);
        $offer = Offer::factory()->create(['available_units' => 1]);

        $result = $repository->decrementAvailableUnits($offer);

        $this->assertTrue($result);
        $this->assertSame(0, $offer->fresh()->available_units);
    }

    public function testItRefusesToDecrementBelowZero(): void
    {
        $repository = $this->app->make(OfferRepository::class);
        $offer = Offer::factory()->create(['available_units' => 0]);

        $result = $repository->decrementAvailableUnits($offer);

        $this->assertFalse($result);
        $this->assertSame(0, $offer->fresh()->available_units);
    }
}
