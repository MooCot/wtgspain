<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Properties\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchRepositoryTest extends TestCase
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

    public function testItReturnsOnlyOffersMatchingCriteria(): void
    {
        $repository = $this->app->make(PropertyRepository::class);
        $supplier = Supplier::factory()->create();

        $matching = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($matching)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $wrongDates = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($wrongDates)->create([
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $notEnoughGuests = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($notEnoughGuests)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 1,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $soldOut = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($soldOut)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 0,
            'expires_at' => now()->addDays(5),
        ]);

        $expired = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($expired)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->subDay(),
        ]);

        $result = $repository->searchWithBestOffer($this->searchCriteria());

        $this->assertCount(1, $result->items());
        $this->assertSame($matching->code, $result->items()[0]->propertyCode);
    }

    public function testItReturnsCheapestOfferPerProperty(): void
    {
        $repository = $this->app->make(PropertyRepository::class);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $property = Property::factory()->create();

        $commonAttributes = [
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ];

        Offer::factory()->for($supplierA)->for($property)->create([...$commonAttributes, 'price' => 90000]);
        Offer::factory()->for($supplierB)->for($property)->create([...$commonAttributes, 'price' => 50000]);

        $result = $repository->searchWithBestOffer($this->searchCriteria());

        $this->assertCount(1, $result->items());
        $this->assertSame(50000, $result->items()[0]->price);
        $this->assertSame($supplierB->code, $result->items()[0]->supplierCode);
    }

    public function testItFiltersByCityWhenProvided(): void
    {
        $repository = $this->app->make(PropertyRepository::class);
        $supplier = Supplier::factory()->create();

        $commonAttributes = [
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ];

        $barcelona = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($barcelona)->create($commonAttributes);

        $madrid = Property::factory()->create(['city' => 'Madrid']);
        Offer::factory()->for($supplier)->for($madrid)->create($commonAttributes);

        $result = $repository->searchWithBestOffer($this->searchCriteria(['city' => 'Barcelona']));

        $this->assertCount(1, $result->items());
        $this->assertSame('Barcelona', $result->items()[0]->propertyCity);
    }
}
