<?php

namespace Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function reservationPayload(array $overrides = []): array
    {
        return collect([
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])->merge($overrides)->all();
    }

    public function testItCreatesReservationAndReturns201(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", $this->reservationPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.client_reference', 'web-order-9f782b1c');
        $this->assertDatabaseCount('reservations', 1);
        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function testItReturnsConflictWhenNoUnitsAvailable(): void
    {
        $offer = Offer::factory()->create(['available_units' => 0]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", $this->reservationPayload());

        $response->assertStatus(409);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function testItValidatesRequiredFields(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", []);

        $response->assertStatus(422);
    }

    public function testItReturns404ForUnknownOffer(): void
    {
        $response = $this->postJson('/api/offers/999999/reservations', $this->reservationPayload());

        $response->assertStatus(404);
    }

    public function testItReturnsCleanBodyOn404RegardlessOfAppDebug(): void
    {
        $response = $this->postJson('/api/offers/999999/reservations', $this->reservationPayload());

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'Not found.']);
    }

    public function testItReturnsJsonValidationErrorsEvenWithoutAcceptHeader(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->post("/api/offers/{$offer->id}/reservations", []);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function testItReturnsJson404EvenWithoutAcceptHeader(): void
    {
        $response = $this->post('/api/offers/999999/reservations', $this->reservationPayload());

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/json');
    }
}
