<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionCourse>
 */
class SectionCourseFactory extends Factory
{
    protected $model = SectionCourse::class;

    public function definition(): array
    {
        $totalHours = fake()->randomElement([30, 45, 60, 90, 120]);
        $hoursPerSession = fake()->randomElement([1, 2, 3]);

        return [
            'section_user_id' => SectionUserSchoolRole::inRandomOrder()->first()?->id
                ?? throw new \RuntimeException('Seed Section before SectionCourse.'),
            'course_id' => Course::inRandomOrder()->first()?->id
                ?? throw new \RuntimeException('Seed Course before SectionCourse.'),
            'total_hours' => $totalHours,
            'hours_per_session' => $hoursPerSession,
            'name' => fake()->words(3, true),
            'reference' => strtoupper(fake()->lexify('SC-????')),
            'description' => fake()->sentence(),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
