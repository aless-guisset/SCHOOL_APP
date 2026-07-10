<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSchoolRole>
 */
class UserSchoolRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()->id,
            'school_id' => School::inRandomOrder()->first()->id,
            'role_id' => Role::inRandomOrder()->first()->id,
            'status' => 'A',
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
