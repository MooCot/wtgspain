<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\OfferRepository;
use App\Application\Reservations\Exceptions\OfferUnavailableException;
use App\Application\Reservations\Ports\ReservationRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentReservationRepository implements ReservationRepository
{
    public function __construct(
        private readonly OfferRepository $offers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForOffer(Offer $offer, array $attributes): Reservation
    {
        $existing = Reservation::query()->where('client_reference', $attributes['client_reference'])->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($offer, $attributes) {
                if (! $this->offers->decrementAvailableUnits($offer)) {
                    throw new OfferUnavailableException("Offer {$offer->id} has no available units.");
                }

                $values = collect($attributes)->merge(['offer_id' => $offer->id])->all();

                return Reservation::query()->create($values);
            });
        } catch (UniqueConstraintViolationException $e) {
            return Reservation::query()->where('client_reference', $attributes['client_reference'])->first() ?? throw $e;
        }
    }
}
