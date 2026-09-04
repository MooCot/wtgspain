<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EloquentPropertyRepository implements PropertyRepository
{
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

        // TTL-обмежена свіжість, не активна інвалідація: available_units/ціни
        // міняються імпортами й бронюваннями, тому короткий TTL (30с) — свідомий
        // компроміс між навантаженням на БД і актуальністю, а не недогляд.
        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($criteria) {
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
