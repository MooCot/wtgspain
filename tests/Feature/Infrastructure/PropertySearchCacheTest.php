<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Offers\Ports\OfferRepository;
use App\Application\Properties\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertySearchCacheTest extends TestCase
{
    use RefreshDatabase;

    private function searchCriteria(array $overrides = []): array
    {
        return collect([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
            'city' => null,
            'page' => 1,
            'per_page' => 15,
        ])->merge($overrides)->all();
    }

    public function testCacheIsNotInvalidatedByWritesBypassingTheRepository(): void
    {
        $repository = $this->app->make(PropertyRepository::class);
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();
        Offer::factory()->for($supplier)->for($property)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $first = $repository->searchWithBestOffer($this->searchCriteria());
        $this->assertCount(1, $first->items());

        DB::table('offers')->update(['available_units' => 0]);

        $second = $repository->searchWithBestOffer($this->searchCriteria());

        $this->assertCount(1, $second->items());
    }

    public function testCacheIsInvalidatedWhenOfferAvailabilityChangesViaRepository(): void
    {
        $propertyRepository = $this->app->make(PropertyRepository::class);
        $offerRepository = $this->app->make(OfferRepository::class);
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();
        $offer = Offer::factory()->for($supplier)->for($property)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 1,
            'expires_at' => now()->addDays(5),
        ]);

        $first = $propertyRepository->searchWithBestOffer($this->searchCriteria());
        $this->assertCount(1, $first->items());

        $offerRepository->decrementAvailableUnits($offer);

        $second = $propertyRepository->searchWithBestOffer($this->searchCriteria());

        $this->assertCount(0, $second->items());
    }

    public function testCacheIsInvalidatedWhenNewOfferIsCreatedViaRepository(): void
    {
        $propertyRepository = $this->app->make(PropertyRepository::class);
        $offerRepository = $this->app->make(OfferRepository::class);
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create();

        $first = $propertyRepository->searchWithBestOffer($this->searchCriteria());
        $this->assertCount(0, $first->items());

        $offerRepository->updateOrCreate($supplier, $property, 'offer-new', [
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 50000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $second = $propertyRepository->searchWithBestOffer($this->searchCriteria());

        $this->assertCount(1, $second->items());
    }

    public function testCacheIsNotInvalidatedByNoOpDecrement(): void
    {
        $propertyRepository = $this->app->make(PropertyRepository::class);
        $offerRepository = $this->app->make(OfferRepository::class);
        $supplier = Supplier::factory()->create();

        $propertyA = Property::factory()->create();
        $offerA = Offer::factory()->for($supplier)->for($propertyA)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $propertyB = Property::factory()->create();
        $offerB = Offer::factory()->for($supplier)->for($propertyB)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 0,
            'expires_at' => now()->addDays(5),
        ]);

        $first = $propertyRepository->searchWithBestOffer($this->searchCriteria());
        $this->assertCount(1, $first->items());

        DB::table('offers')->where('id', $offerA->id)->update(['available_units' => 0]);

        $offerRepository->decrementAvailableUnits($offerB);

        $second = $propertyRepository->searchWithBestOffer($this->searchCriteria());

        $this->assertCount(1, $second->items());
    }

    public function testDifferentCriteriaAreNotCachedTogether(): void
    {
        $repository = $this->app->make(PropertyRepository::class);
        $supplier = Supplier::factory()->create();

        $barcelona = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($barcelona)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $repository->searchWithBestOffer($this->searchCriteria());
        $filtered = $repository->searchWithBestOffer($this->searchCriteria(['city' => 'Madrid']));

        $this->assertCount(0, $filtered->items());
    }
}
