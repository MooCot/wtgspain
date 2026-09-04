<?php

namespace App\Application\Imports\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Import;

interface ImportRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Import $import, array $attributes): Import;
}
