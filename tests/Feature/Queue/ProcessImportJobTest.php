<?php

namespace Tests\Feature\Queue;

use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use App\Infrastructure\Queue\Jobs\ProcessImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function testFailedMarksImportAsFailedAfterRetriesAreExhausted(): void
    {
        $supplier = Supplier::factory()->create();
        $import = Import::factory()->for($supplier)->create([
            'status' => 'processing',
            'total_offers' => 0,
        ]);

        $offers = collect([
            ['external_id' => 'offer-a', 'property' => ['code' => 'X', 'name' => 'Y', 'city' => 'Z']],
        ]);

        $job = new ProcessImportJob($import, $offers);
        $job->failed(new \TypeError('Argument #3 ($externalId) must be of type string, null given'));

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Failed, $fresh->status);
        $this->assertNotNull($fresh->error);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(1, $fresh->total_offers);
    }
}
