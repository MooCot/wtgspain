<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function testItSeedsBothSuppliers(): void
    {
        $this->seed(SupplierSeeder::class);

        $this->assertDatabaseCount('suppliers', 2);
        $this->assertTrue(Supplier::query()->where('code', 'supplier-a')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'supplier-b')->exists());
    }

    public function testItIsIdempotentWhenRunTwice(): void
    {
        $this->seed(SupplierSeeder::class);
        $this->seed(SupplierSeeder::class);

        $this->assertDatabaseCount('suppliers', 2);
    }
}
