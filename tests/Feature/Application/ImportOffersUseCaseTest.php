<?php

namespace Tests\Feature\Application;

use App\Application\Imports\ImportOffersUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOffersUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private function offerPayload(array $overrides = []): array
    {
        return collect([
            'external_id' => 'offer-'.uniqid(),
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
            'expires_at' => '2026-09-10 23:59:59',
        ])->merge($overrides)->all();
    }

    public function testItProcessesOffersAndMarksImportCompleted(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $offers = collect([
            $this->offerPayload(['external_id' => 'offer-a']),
        ]);

        $useCase = $this->app->make(ImportOffersUseCase::class);
        $result = $useCase->handle($import, $supplier, $offers);

        $this->assertSame(ImportStatus::Completed, $result->status);
        $this->assertSame(1, $result->total_offers);
        $this->assertSame(1, $result->processed_offers);
        $this->assertNotNull($result->completed_at);
        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseCount('properties', 1);
    }

    public function testItReusesExistingPropertyByCode(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $offers = collect([
            $this->offerPayload(['external_id' => 'offer-a']),
            $this->offerPayload(['external_id' => 'offer-b']),
        ]);

        $useCase = $this->app->make(ImportOffersUseCase::class);
        $useCase->handle($import, $supplier, $offers);

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('offers', 2);
    }

    public function testItMarksImportFailedOnError(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $offers = collect([
            $this->offerPayload(['property' => null]),
        ]);

        $useCase = $this->app->make(ImportOffersUseCase::class);
        $result = $useCase->handle($import, $supplier, $offers);

        $this->assertSame(ImportStatus::Failed, $result->status);
        $this->assertSame('Offer payload missing property data.', $result->error);
        $this->assertNotNull($result->completed_at);
    }

    public function testItDoesNotSwallowProgrammerErrors(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $offers = collect([
            $this->offerPayload(['external_id' => null]),
        ]);

        $useCase = $this->app->make(ImportOffersUseCase::class);

        $this->expectException(\TypeError::class);

        $useCase->handle($import, $supplier, $offers);
    }

    public function testItStatesScopeOfPartialFailureInErrorMessage(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $offers = collect([
            $this->offerPayload(['external_id' => 'offer-a']),
            $this->offerPayload(['external_id' => 'offer-b']),
            $this->offerPayload(['property' => null]),
        ]);

        $useCase = $this->app->make(ImportOffersUseCase::class);
        $result = $useCase->handle($import, $supplier, $offers);

        $this->assertSame(ImportStatus::Failed, $result->status);
        $this->assertSame(2, $result->processed_offers);
        $this->assertSame(3, $result->total_offers);
        $this->assertSame(
            'Offer payload missing property data. (2 of 3 offers were already processed and remain in the system.)',
            $result->error,
        );
        $this->assertDatabaseCount('offers', 2);
    }

    public function testItHidesRawDriverErrorsBehindGenericMessage(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create(['status' => 'pending']);

        $offers = collect([
            $this->offerPayload(['property' => ['code' => 'BCN-0001', 'city' => 'Barcelona']]),
        ]);

        $useCase = $this->app->make(ImportOffersUseCase::class);
        $result = $useCase->handle($import, $supplier, $offers);

        $this->assertSame(ImportStatus::Failed, $result->status);
        $this->assertSame('Internal error while processing offers.', $result->error);
        $this->assertStringNotContainsString('SQL:', (string) $result->error);
    }
}
