<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

interface OfferRepository
{
    /**
     * Idempotently creates or updates an Offer by (supplier, external_id).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Supplier $supplier, Property $property, string $externalId, array $attributes): Offer;

    /**
     * Atomically decrements available_units. Returns false if available_units is already 0.
     */
    public function decrementAvailableUnits(Offer $offer): bool;
}
