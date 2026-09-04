<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use App\Infrastructure\Persistence\Eloquent\Models\Property;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('+1 week', '+2 months');
        $checkOut = (clone $checkIn)->modify('+'.$this->faker->numberBetween(2, 7).' days');

        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'external_id' => $this->faker->unique()->uuid(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'max_guests' => $this->faker->numberBetween(1, 6),
            'price' => $this->faker->numberBetween(5000, 150000),
            'currency' => 'EUR',
            'available_units' => $this->faker->numberBetween(1, 5),
            'expires_at' => now()->addDays(30),
        ];
    }
}
