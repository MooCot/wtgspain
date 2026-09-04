<?php

namespace Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertiesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsMatchingPropertiesWithBestOffer(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $property = Property::factory()->create([
            'code' => 'BCN-0001',
            'name' => 'Apartment near Sagrada Familia',
            'city' => 'Barcelona',
        ]);
        Offer::factory()->for($supplier)->for($property)->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $response = $this->getJson('/api/properties?'.http_build_query([
            'city' => 'Barcelona',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.code', 'BCN-0001');
        $response->assertJsonPath('data.0.city', 'Barcelona');
        $response->assertJsonPath('data.0.best_offer.supplier', 'supplier-a');
        $response->assertJsonPath('data.0.best_offer.price', 72500);
        $response->assertJsonStructure([
            'data' => [['code', 'name', 'city', 'best_offer' => ['id', 'supplier', 'price', 'currency', 'available_units', 'expires_at']]],
            'next',
            'prev',
            'per_page',
        ]);
    }

    public function testItRequiresCheckInCheckOutGuests(): void
    {
        $response = $this->getJson('/api/properties');

        $response->assertStatus(422);
    }

    public function testItFiltersByCityWhenProvided(): void
    {
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

        $response = $this->getJson('/api/properties?'.http_build_query([
            'city' => 'Madrid',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.city', 'Madrid');
    }
}
