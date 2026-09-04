<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Properties\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EloquentPropertyRepository implements PropertyRepository
{
    public const SEARCH_CACHE_TAG = 'properties:search';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByCode(string $code, array $attributes): Property
    {
        $existing = Property::query()->where('code', $code)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Property::query()->create(collect($attributes)->merge(['code' => $code])->all());
        } catch (UniqueConstraintViolationException $e) {
            return Property::query()->where('code', $code)->first() ?? throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function searchWithBestOffer(array $criteria): LengthAwarePaginator
    {
        $criteria = collect($criteria);
        $cacheKey = 'properties:search:'.md5(json_encode($criteria->sortKeys()->all()));

        return Cache::tags([self::SEARCH_CACHE_TAG])->remember($cacheKey, now()->addMinutes(5), function () use ($criteria) {
            $ranked = DB::table('offers')
                ->join('properties', 'properties.id', '=', 'offers.property_id')
                ->join('suppliers', 'suppliers.id', '=', 'offers.supplier_id')
                ->select([
                    'offers.id as offer_id',
                    'offers.price',
                    'offers.currency',
                    'offers.available_units',
                    'offers.expires_at',
                    'properties.code as property_code',
                    'properties.name as property_name',
                    'properties.city as property_city',
                    'suppliers.code as supplier_code',
                ])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY offers.property_id ORDER BY offers.price ASC) as rn')
                ->where('offers.check_in', $criteria->get('check_in'))
                ->where('offers.check_out', $criteria->get('check_out'))
                ->where('offers.max_guests', '>=', $criteria->get('guests'))
                ->where('offers.available_units', '>', 0)
                ->where('offers.expires_at', '>', now())
                ->when($criteria->get('city'), fn ($query, $city) => $query->where('properties.city', $city));

            return DB::query()
                ->fromSub($ranked, 'ranked')
                ->where('rn', 1)
                ->orderBy('price')
                ->paginate(
                    perPage: $criteria->get('per_page', 15),
                    page: $criteria->get('page', 1),
                );
        });
    }
}
