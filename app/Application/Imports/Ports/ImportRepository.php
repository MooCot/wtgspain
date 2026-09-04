<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;

interface ImportRepository
{
    /**
     * Looks up an Import by its unique (supplier, external_import_id) pair.
     */
    public function findBySupplierAndExternalImportId(Supplier $supplier, string $externalImportId): ?Import;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Import;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Import $import, array $attributes): Import;
}
