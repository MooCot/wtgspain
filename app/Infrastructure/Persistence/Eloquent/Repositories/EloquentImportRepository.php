<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\ImportRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

class EloquentImportRepository implements ImportRepository
{
    public function findBySupplierAndExternalImportId(Supplier $supplier, string $externalImportId): ?Import
    {
        return Import::query()
            ->where('supplier_id', $supplier->id)
            ->where('external_import_id', $externalImportId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Import
    {
        return Import::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Import $import, array $attributes): Import
    {
        $import->update($attributes);

        return $import->fresh();
    }
}
