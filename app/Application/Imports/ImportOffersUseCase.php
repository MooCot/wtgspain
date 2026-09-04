<?php

namespace App\Application\Imports;

use App\Application\Imports\Ports\ImportRepository;
use App\Application\Imports\Ports\OfferRepository;
use App\Application\Imports\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Support\Collection;

class ImportOffersUseCase
{
    public function __construct(
        private readonly PropertyRepository $properties,
        private readonly OfferRepository $offers,
        private readonly ImportRepository $imports,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $offersPayload
     */
    public function handle(Import $import, Supplier $supplier, Collection $offersPayload): Import
    {
        $processed = 0;

        try {
            foreach ($offersPayload as $offerData) {
                if (! is_array($offerData['property'] ?? null)) {
                    throw new \InvalidArgumentException('Offer payload missing property data.');
                }

                /** @var array<string, mixed> $propertyPayload */
                $propertyPayload = $offerData['property'];

                $property = $this->properties->findOrCreateByCode(
                    $propertyPayload['code'],
                    collect($propertyPayload)->only(['name', 'city'])->all(),
                );

                $this->offers->updateOrCreate(
                    $supplier,
                    $property,
                    $offerData['external_id'],
                    collect($offerData)->except(['external_id', 'property'])->all(),
                );

                $processed++;
            }

            return $this->imports->update($import, [
                'status' => 'completed',
                'total_offers' => $offersPayload->count(),
                'processed_offers' => $processed,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return $this->imports->update($import, [
                'status' => 'failed',
                'total_offers' => $offersPayload->count(),
                'processed_offers' => $processed,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
