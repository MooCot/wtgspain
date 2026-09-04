<?php

namespace App\Application\Reservations\Ports;

use App\Application\Reservations\Exceptions\OfferUnavailableException;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Reservation;

interface ReservationRepository
{
    /**
     * Atomically decrements available_units and creates a Reservation in a
     * single transaction — protects against double-booking the last unit (C4).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws OfferUnavailableException if available_units is already 0
     */
    public function createForOffer(Offer $offer, array $attributes): Reservation;
}
