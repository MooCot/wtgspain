<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Imports\Ports\SupplierRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRepositoryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function testFindByCodeCachesResultAcrossCalls(): void
    {
        $repository = $this->app->make(SupplierRepository::class);
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $first = $repository->findByCode('supplier-a');
        $this->assertNotNull($first);

        $supplier->delete();

        $second = $repository->findByCode('supplier-a');

        $this->assertNotNull($second);
        $this->assertSame($supplier->id, $second->id);
    }

    public function testFindByCodeDoesNotCacheMissesForever(): void
    {
        $repository = $this->app->make(SupplierRepository::class);

        $this->assertNull($repository->findByCode('does-not-exist-yet'));

        Supplier::factory()->create(['code' => 'does-not-exist-yet']);

        $this->assertNotNull($repository->findByCode('does-not-exist-yet'));
    }
}
