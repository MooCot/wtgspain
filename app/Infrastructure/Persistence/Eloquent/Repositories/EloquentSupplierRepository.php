<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\SupplierRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Support\Facades\Cache;

class EloquentSupplierRepository implements SupplierRepository
{
    public function findByCode(string $code): ?Supplier
    {
        return Cache::remember(
            "supplier:{$code}",
            now()->addHour(),
            fn () => Supplier::query()->where('code', $code)->first(),
        );
    }
}
