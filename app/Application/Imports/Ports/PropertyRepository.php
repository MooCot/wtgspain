<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PropertyRepository
{
    /**
     * Idempotently finds or creates a Property by code — C3.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByCode(string $code, array $attributes): Property;

    /**
     * Searches for the cheapest actual offer per Property — P5 (predicate),
     * selection + sorting + pagination at the SQL level, not PHP collections.
     *
     * @param  array<string, mixed>  $criteria  check_in, check_out, guests, city, page, per_page
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function searchWithBestOffer(array $criteria): LengthAwarePaginator;
}
