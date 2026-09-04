<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Imports\Ports\ImportRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportRepositoryRaceTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsExistingImportWhenCreateRacesUniqueConstraint(): void
    {
        $repository = $this->app->make(ImportRepository::class);
        $supplier = Supplier::factory()->create();

        $existing = Import::factory()->for($supplier)->create([
            'external_import_id' => 'import-1',
        ]);

        $result = $repository->create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'import-1',
            'sent_at' => now(),
            'status' => 'pending',
        ]);

        $this->assertSame($existing->id, $result->id);
        $this->assertDatabaseCount('imports', 1);
    }
}
