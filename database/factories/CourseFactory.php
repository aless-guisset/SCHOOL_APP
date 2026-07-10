<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
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
            'name' => fake()->randomElement(['Mathematics', 'Science', 'History', 'Literature', 'Physics', 'Chemistry', 'Geography', 'Arts']),
            'reference' => strtoupper(fake()->lexify('CRS###')),
            'description' => fake()->sentence(),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
