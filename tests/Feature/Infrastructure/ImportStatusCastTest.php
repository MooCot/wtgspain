<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportStatusCastTest extends TestCase
{
    use RefreshDatabase;

    public function testStatusCastsToEnumInstance(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => ImportStatus::Completed]);

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(ImportStatus::Completed, $import->fresh()->status);
    }

    public function testStatusAcceptsRawStringOnWrite(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $this->assertSame(ImportStatus::Pending, $import->fresh()->status);
    }
}
