<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timesheet>
 */
class TimesheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schedule = Schedule::inRandomOrder()->first();

        return [
            'user_school_role_id' => UserSchoolRole::inRandomOrder()->first()->id,
            'schedule_id' => $schedule->id,
            'subject_id' => Subject::inRandomOrder()->first()->id,
            'classroom_id' => Classroom::inRandomOrder()->first()->id,
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'hours_done' => $schedule?->sectionCourse?->hours_per_session ?? 1,
            'reference' => strtoupper(fake()->lexify('TS####')),
            'description' => fake()->sentence(),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
