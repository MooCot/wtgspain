<?php

namespace Tests\Feature\Application;

use App\Application\Imports\RegisterImportUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterImportUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function testItCreatesNewImport(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $useCase = $this->app->make(RegisterImportUseCase::class);
        $import = $useCase->handle('supplier-a', 'import-2026-09-01-001', '2026-09-01T10:00:00Z');

        $this->assertDatabaseCount('imports', 1);
        $this->assertSame($supplier->id, $import->supplier_id);
        $this->assertSame('pending', $import->status);
        $this->assertTrue($import->wasRecentlyCreated);
    }

    public function testItReturnsExistingImportIdempotently(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);
        $useCase = $this->app->make(RegisterImportUseCase::class);

        $first = $useCase->handle('supplier-a', 'import-2026-09-01-001', '2026-09-01T10:00:00Z');
        $second = $useCase->handle('supplier-a', 'import-2026-09-01-001', '2026-09-01T10:00:00Z');

        $this->assertDatabaseCount('imports', 1); // C1 — не дублює
        $this->assertSame($first->id, $second->id);
        $this->assertFalse($second->wasRecentlyCreated);
    }

    public function testItThrowsForUnknownSupplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $useCase = $this->app->make(RegisterImportUseCase::class);
        $useCase->handle('unknown-supplier', 'import-1', '2026-09-01T10:00:00Z');
    }
}
