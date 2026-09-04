<?php

namespace Tests\Feature\Application;

use App\Application\Properties\SearchPropertiesUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPropertiesUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function testItDelegatesSearchToPropertyRepository(): void
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create(['city' => 'Barcelona']);
        Offer::factory()->for($supplier)->for($property)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $useCase = app(SearchPropertiesUseCase::class);
        $result = $useCase->handle([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
            'city' => null,
            'page' => 1,
            'per_page' => 15,
        ]);

        $this->assertCount(1, $result->items());
        $this->assertSame($property->code, $result->items()[0]->property_code);
    }
}
