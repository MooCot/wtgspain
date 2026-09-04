<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\OfferRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Database\UniqueConstraintViolationException;

class EloquentOfferRepository implements OfferRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Supplier $supplier, Property $property, string $externalId, array $attributes): Offer
    {
        $lookup = ['supplier_id' => $supplier->id, 'external_id' => $externalId];
        $values = collect($attributes)->merge(['property_id' => $property->id])->all();

        $existing = Offer::query()->where($lookup)->first();

        if ($existing !== null) {
            $existing->update($values);

            return $existing->fresh();
        }

        try {
            return Offer::query()->create(collect($lookup)->merge($values)->all());
        } catch (UniqueConstraintViolationException $e) {
            $recovered = Offer::query()->where($lookup)->first();

            if ($recovered === null) {
                throw $e;
            }

            $recovered->update($values);

            return $recovered->fresh();
        }
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
