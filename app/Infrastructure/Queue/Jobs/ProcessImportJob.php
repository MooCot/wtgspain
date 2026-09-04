<?php

namespace App\Infrastructure\Queue\Jobs;

use App\Application\Imports\ImportOffersUseCase;
use App\Application\Imports\Ports\ImportRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\ImportStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  Collection<int, array<string, mixed>>  $offersPayload
     */
    public function __construct(
        public readonly Import $import,
        public readonly Collection $offersPayload,
    ) {}

    public function handle(ImportOffersUseCase $useCase): void
    {
        $useCase->handle($this->import, $this->import->supplier, $this->offersPayload);
    }

    /**
     * Called by the queue worker once retries are exhausted. ImportOffersUseCase
     * only catches \Exception (by design, so \Error propagates to this point
     * instead of being silently swallowed) — without this, an import that dies
     * this way would be stuck at "processing" forever instead of "failed".
     */
    public function failed(\Throwable $exception): void
    {
        App::make(ImportRepository::class)->update($this->import, [
            'status' => ImportStatus::Failed,
            'total_offers' => $this->offersPayload->count(),
            'error' => 'Import processing failed after all retries were exhausted.',
            'completed_at' => now(),
        ]);
    }
}
