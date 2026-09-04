<?php

namespace App\Infrastructure\Queue\Jobs;

use App\Application\Imports\ImportOffersUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

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
}
