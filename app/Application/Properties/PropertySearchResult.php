<?php

namespace App\Application\Properties;

/**
 * DTO — one row of a property search result. No behavior by design;
 * pairs with PropertyRepository::searchWithBestOffer().
 */
final class PropertySearchResult
{
    public function __construct(
        public readonly int $offerId,
        public readonly string $propertyCode,
        public readonly string $propertyName,
        public readonly string $propertyCity,
        public readonly string $supplierCode,
        public readonly int $price,
        public readonly string $currency,
        public readonly int $availableUnits,
        public readonly string $expiresAt,
    ) {}
}
