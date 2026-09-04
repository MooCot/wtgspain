<?php

namespace App\Infrastructure\Persistence\Eloquent\Models\Casts;

use App\Infrastructure\Persistence\Eloquent\Models\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Combines the `price` + `currency` columns into a single Money value object
 * on read. On write, accepts either a raw int (existing array-based data
 * flow from ImportOffersUseCase) or a Money instance (sets both columns).
 *
 * @implements CastsAttributes<Money, Money|int>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if (! isset($attributes['price'], $attributes['currency'])) {
            return null;
        }

        return new Money((int) $attributes['price'], (string) $attributes['currency']);
    }

    /**
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value instanceof Money) {
            return [
                'price' => $value->amount,
                'currency' => $value->currency,
            ];
        }

        return ['price' => $value];
    }
}
