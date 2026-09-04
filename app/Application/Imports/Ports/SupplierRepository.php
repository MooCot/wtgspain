<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

interface SupplierRepository
{
    public function findByCode(string $code): ?Supplier;
}
