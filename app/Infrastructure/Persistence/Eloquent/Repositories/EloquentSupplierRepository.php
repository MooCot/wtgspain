<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\SupplierRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

class EloquentSupplierRepository implements SupplierRepository
{
    public function findByCode(string $code): ?Supplier
    {
        return Supplier::query()->where('code', $code)->first();
    }
}
