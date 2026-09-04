<?php

namespace App\Infrastructure\Http\Resources;

use App\Application\Properties\PropertySearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PropertySearchResult
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->propertyCode,
            'name' => $this->propertyName,
            'city' => $this->propertyCity,
            'best_offer' => [
                'id' => $this->offerId,
                'supplier' => $this->supplierCode,
                'price' => $this->price,
                'currency' => $this->currency,
                'available_units' => $this->availableUnits,
                'expires_at' => $this->expiresAt,
            ],
        ];
    }
}
