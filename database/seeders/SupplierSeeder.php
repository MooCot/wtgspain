<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::query()->firstOrCreate(['code' => 'supplier-a'], ['name' => 'Supplier A']);
        Supplier::query()->firstOrCreate(['code' => 'supplier-b'], ['name' => 'Supplier B']);
    }
}
