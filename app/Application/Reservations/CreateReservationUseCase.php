<?php

namespace App\Application\Reservations;

use App\Application\Reservations\Ports\ReservationRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Reservation;

class CreateReservationUseCase
{
    public function __construct(
        private readonly ReservationRepository $reservations,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Offer $offer, array $attributes): Reservation
    {
        return $this->reservations->createForOffer($offer, $attributes);
    }
}
