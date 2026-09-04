<?php

namespace App\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $property_code
 * @property-read string $property_name
 * @property-read string $property_city
 * @property-read int $offer_id
 * @property-read string $supplier_code
 * @property-read int $price
 * @property-read string $currency
 * @property-read int $available_units
 * @property-read string $expires_at
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->property_code,
            'name' => $this->property_name,
            'city' => $this->property_city,
            'best_offer' => [
                'id' => $this->offer_id,
                'supplier' => $this->supplier_code,
                'price' => $this->price,
                'currency' => $this->currency,
                'available_units' => $this->available_units,
                'expires_at' => $this->expires_at,
            ],
        ];
    }
}
