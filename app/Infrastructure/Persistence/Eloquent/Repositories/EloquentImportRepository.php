<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Imports\Ports\ImportRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;

class EloquentImportRepository implements ImportRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Import $import, array $attributes): Import
    {
        $import->update($attributes);

        return $import->fresh();
    }
}
