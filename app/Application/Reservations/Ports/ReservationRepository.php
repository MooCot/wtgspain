<?php

namespace App\Application\Reservations\Ports;

use App\Application\Reservations\Exceptions\OfferUnavailableException;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Reservation;

interface ReservationRepository
{
    /**
     * Атомарно декрементує available_units і створює Reservation в одній
     * транзакції — захист від double-booking останньої одиниці (C4).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws OfferUnavailableException якщо available_units вже 0
     */
    public function createForOffer(Offer $offer, array $attributes): Reservation;
}
