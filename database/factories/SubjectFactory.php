<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::inRandomOrder()->first()->id,
            'name' => fake()->randomElement(['Algebra', 'Geometry', 'Trigonometry', 'Calculus', 'Biology', 'Ecology', 'Civics', 'Poetry', 'Grammar']),
            'reference' => strtoupper(fake()->lexify('SUB###')),
            'description' => fake()->sentence(),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
