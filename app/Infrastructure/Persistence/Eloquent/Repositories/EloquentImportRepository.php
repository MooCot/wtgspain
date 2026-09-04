<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\ImportRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Database\UniqueConstraintViolationException;

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
        try {
            return Import::query()->create($attributes);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->findBySupplierAndExternalImportId(
                Supplier::query()->findOrFail($attributes['supplier_id']),
                $attributes['external_import_id'],
            );

            return $existing ?? throw $e;
        }
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
