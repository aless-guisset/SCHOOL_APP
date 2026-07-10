<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::inRandomOrder()->first()?->id,
            'name' => 'Section '.fake()->unique()->bothify('??-##'),
            'reference' => strtoupper(fake()->lexify('SEC-????')),
            'description' => fake()->sentence(),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
