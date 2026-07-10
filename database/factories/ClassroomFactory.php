<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::inRandomOrder()->first()->id,
            'name' => 'Room '.fake()->bothify('##?'),
            'reference' => strtoupper(fake()->lexify('CLS###')),
            'description' => fake()->sentence(),
            'location' => 'Building '.fake()->randomLetter().', Floor '.fake()->numberBetween(1, 4),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
