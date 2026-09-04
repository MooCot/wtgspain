<?php

namespace App\Application\Reservations\Ports;

use App\Application\Reservations\Exceptions\OfferUnavailableException;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Reservation;

interface ReservationRepository
{
    /**
     * Idempotent by client_reference — a repeated call with the same
     * client_reference returns the existing Reservation instead of creating
     * a duplicate. On the first call, atomically decrements available_units
     * and creates a Reservation in a single transaction — protects against
     * double-booking the last unit.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws OfferUnavailableException if available_units is already 0
     */
    public function createForOffer(Offer $offer, array $attributes): Reservation;
}
