<?php

namespace Tests\Feature\Application;

use App\Application\Imports\RegisterImportUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterImportUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_new_import(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $useCase = app(RegisterImportUseCase::class);
        $import = $useCase->handle('supplier-a', 'import-2026-09-01-001', '2026-09-01T10:00:00Z');

        $this->assertDatabaseCount('imports', 1);
        $this->assertSame($supplier->id, $import->supplier_id);
        $this->assertSame('pending', $import->status);
        $this->assertTrue($import->wasRecentlyCreated);
    }

    public function test_it_returns_existing_import_idempotently(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);
        $useCase = app(RegisterImportUseCase::class);

        $first = $useCase->handle('supplier-a', 'import-2026-09-01-001', '2026-09-01T10:00:00Z');
        $second = $useCase->handle('supplier-a', 'import-2026-09-01-001', '2026-09-01T10:00:00Z');

        $this->assertDatabaseCount('imports', 1); // C1 — не дублює
        $this->assertSame($first->id, $second->id);
        $this->assertFalse($second->wasRecentlyCreated);
    }

    public function test_it_throws_for_unknown_supplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $useCase = app(RegisterImportUseCase::class);
        $useCase->handle('unknown-supplier', 'import-1', '2026-09-01T10:00:00Z');
    }
}
