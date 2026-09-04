<?php

namespace Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function importPayload(array $overrides = []): array
    {
        return collect([
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 72500,
                    'currency' => 'EUR',
                    'available_units' => 2,
                    'expires_at' => '2026-09-10T23:59:59Z',
                ],
            ],
        ])->merge($overrides)->all();
    }

    public function testItAcceptsImportAndProcessesItSynchronouslyViaSyncQueue(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);

        $response = $this->postJson('/api/imports', $this->importPayload());

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending');

        $import = Import::query()->firstOrFail();
        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->total_offers);
        $this->assertSame(1, $import->processed_offers);
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('offers', 1);
    }

    public function testItIsIdempotentOnDuplicateSubmission(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);

        $first = $this->postJson('/api/imports', $this->importPayload());
        $second = $this->postJson('/api/imports', $this->importPayload());

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('imports', 1);
        $this->assertDatabaseCount('offers', 1);
    }

    public function testItRejectsUnknownSupplier(): void
    {
        $response = $this->postJson('/api/imports', $this->importPayload(['supplier' => 'unknown-supplier']));

        $response->assertStatus(422);
    }

    public function testItShowsFullImportStatus(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->create([
            'external_import_id' => 'import-1',
            'status' => 'completed',
            'total_offers' => 3,
            'processed_offers' => 3,
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $import->id);
        $response->assertJsonPath('data.supplier', 'supplier-a');
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.total_offers', 3);
    }

    public function testItReturns404ForUnknownImport(): void
    {
        $response = $this->getJson('/api/imports/999999');

        $response->assertStatus(404);
    }
}
