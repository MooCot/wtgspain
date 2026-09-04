<?php

namespace App\Infrastructure\Persistence\Eloquent\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Guarantees a true date-only round-trip: stored as exactly `Y-m-d` (no time
 * component, ever, regardless of what time was in the input), read back as
 * a Carbon instance truncated to start-of-day.
 *
 * @implements CastsAttributes<Carbon, Carbon|string>
 */
class DateOnlyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }
}
