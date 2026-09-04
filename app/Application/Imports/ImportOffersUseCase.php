<?php

namespace App\Application\Imports;

use App\Application\Imports\Exceptions\InvalidOfferPayloadException;
use App\Application\Imports\Ports\ImportRepository;
use App\Application\Offers\Ports\OfferRepository;
use App\Application\Properties\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\ImportStatus;
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
                    throw new InvalidOfferPayloadException('Offer payload missing property data.');
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
                'status' => ImportStatus::Completed,
                'total_offers' => $offersPayload->count(),
                'processed_offers' => $processed,
                'completed_at' => now(),
            ]);
        } catch (InvalidOfferPayloadException $e) {
            return $this->imports->update($import, [
                'status' => ImportStatus::Failed,
                'total_offers' => $offersPayload->count(),
                'processed_offers' => $processed,
                'error' => $this->formatError($e->getMessage(), $processed, $offersPayload->count()),
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            report($e);

            return $this->imports->update($import, [
                'status' => ImportStatus::Failed,
                'total_offers' => $offersPayload->count(),
                'processed_offers' => $processed,
                'error' => $this->formatError('Internal error while processing offers.', $processed, $offersPayload->count()),
                'completed_at' => now(),
            ]);
        }
    }

    private function formatError(string $message, int $processed, int $total): string
    {
        if ($processed === 0) {
            return $message;
        }

        return "{$message} ({$processed} of {$total} offers were already processed and remain in the system.)";
    }
}
