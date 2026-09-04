<?php

namespace App\Application\Properties\Ports;

use App\Application\Properties\PropertySearchResult;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PropertyRepository
{
    /**
     * Idempotently finds or creates a Property by code.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByCode(string $code, array $attributes): Property;

    /**
     * Searches for the cheapest actual offer per Property — selection,
     * sorting and pagination happen at the SQL level, not in PHP collections.
     *
     * @param  array<string, mixed>  $criteria  check_in, check_out, guests, city, page, per_page
     * @return LengthAwarePaginator<int, PropertySearchResult>
     */
    public function searchWithBestOffer(array $criteria): LengthAwarePaginator;
}
