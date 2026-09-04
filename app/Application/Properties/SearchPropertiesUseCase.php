<?php

namespace App\Application\Properties;

use App\Application\Properties\Ports\PropertyRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchPropertiesUseCase
{
    public function __construct(
        private readonly PropertyRepository $properties,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function handle(array $criteria): LengthAwarePaginator
    {
        return $this->properties->searchWithBestOffer($criteria);
    }
}
