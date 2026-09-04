<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'external_import_id' => $this->faker->unique()->uuid(),
            'sent_at' => now(),
            'status' => 'pending',
            'total_offers' => 0,
            'processed_offers' => 0,
            'error' => null,
            'completed_at' => null,
        ];
    }
}
