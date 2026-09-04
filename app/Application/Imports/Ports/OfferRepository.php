<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

interface OfferRepository
{
    /**
     * Ідемпотентно створює або оновлює Offer за (supplier, external_id) — C2.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Supplier $supplier, Property $property, string $externalId, array $attributes): Offer;

    /**
     * Атомарно декрементує available_units. false, якщо available_units вже 0 — C4/P4.
     */
    public function decrementAvailableUnits(Offer $offer): bool;
}
