<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Property;

interface PropertyRepository
{
    /**
     * Ідемпотентно знаходить або створює Property за code — C3.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByCode(string $code, array $attributes): Property;
}
