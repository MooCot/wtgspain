<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Property;

class EloquentPropertyRepository implements PropertyRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByCode(string $code, array $attributes): Property
    {
        return Property::query()->firstOrCreate(['code' => $code], $attributes);
    }
}
