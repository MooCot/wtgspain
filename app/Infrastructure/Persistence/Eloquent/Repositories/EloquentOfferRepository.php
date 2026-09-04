<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\OfferRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

class EloquentOfferRepository implements OfferRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Supplier $supplier, Property $property, string $externalId, array $attributes): Offer
    {
        $values = collect($attributes)
            ->merge(['property_id' => $property->id])
            ->all();

        return Offer::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'external_id' => $externalId,
            ],
            $values
        );
    }

    public function decrementAvailableUnits(Offer $offer): bool
    {
        $affected = Offer::query()
            ->where('id', $offer->id)
            ->where('available_units', '>', 0)
            ->decrement('available_units');

        return $affected > 0;
    }
}
