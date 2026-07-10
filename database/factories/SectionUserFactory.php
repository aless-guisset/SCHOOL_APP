<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\UserSchoolRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionUserFactory extends Factory
{
    protected $model = SectionUserSchoolRole::class;

    public function definition(): array
    {
        return [
            'section_id' => Section::inRandomOrder()->first()?->id
                ?? throw new \RuntimeException('Seed Section before SectionUser.'),
            'user_school_role_id' => UserSchoolRole::inRandomOrder()->first()?->id
                ?? throw new \RuntimeException('Seed UserSchoolRole before SectionUser.'),
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
