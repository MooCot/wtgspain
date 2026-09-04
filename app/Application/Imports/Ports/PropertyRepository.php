<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PropertyRepository
{
    /**
     * Ідемпотентно знаходить або створює Property за code — C3.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByCode(string $code, array $attributes): Property;

    /**
     * Пошук найдешевшої актуальної пропозиції на кожну Property — P5 (предикат),
     * вибір найдешевшої + сортування + пагінація на рівні SQL, не PHP-колекціями.
     *
     * @param  array<string, mixed>  $criteria  check_in, check_out, guests, city, page, per_page
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function searchWithBestOffer(array $criteria): LengthAwarePaginator;
}
