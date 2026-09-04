<?php

namespace Tests\Feature\Application;

use App\Application\Reservations\CreateReservationUseCase;
use App\Application\Reservations\Exceptions\OfferUnavailableException;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateReservationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private function reservationAttributes(array $overrides = []): array
    {
        return collect([
            'client_reference' => 'web-order-'.uniqid(),
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])->merge($overrides)->all();
    }

    public function testItCreatesReservationAndDecrementsAvailableUnits(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $useCase = $this->app->make(CreateReservationUseCase::class);
        $reservation = $useCase->handle($offer, $this->reservationAttributes());

        $this->assertDatabaseCount('reservations', 1);
        $this->assertSame($offer->id, $reservation->offer_id);
        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function testItIsIdempotentByClientReference(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);
        $attributes = $this->reservationAttributes(['client_reference' => 'web-order-dup-1']);

        $useCase = $this->app->make(CreateReservationUseCase::class);
        $first = $useCase->handle($offer, $attributes);
        $second = $useCase->handle($offer, $attributes);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('reservations', 1);
        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function testItThrowsWhenNoUnitsAvailable(): void
    {
        $offer = Offer::factory()->create(['available_units' => 0]);

        $this->expectException(OfferUnavailableException::class);

        $useCase = $this->app->make(CreateReservationUseCase::class);

        try {
            $useCase->handle($offer, $this->reservationAttributes());
        } finally {
            $this->assertDatabaseCount('reservations', 0);
            $this->assertSame(0, $offer->fresh()->available_units);
        }
    }
}
