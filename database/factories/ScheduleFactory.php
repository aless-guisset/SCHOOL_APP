<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\SectionCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        $startHour = fake()->numberBetween(7, 18);
        $endHour = min($startHour + fake()->numberBetween(1, 3), 21);

        return [
            'section_course_id' => SectionCourse::factory(),
            'name' => fake()->randomElement(['Morning Class', 'Afternoon Session', 'Evening Lab', 'Weekend Review']),
            'day_of_week' => fake()->numberBetween(1, 7),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time'   => sprintf('%02d:00:00', $endHour),
            'reference' => strtoupper(fake()->lexify('SCH-????')),
            'description' => fake()->sentence(),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
