<?php

namespace Database\Seeders;

use App\Models\SectionCourse;
use Illuminate\Database\Seeder;

class SectionCourseSeeder extends Seeder
{
    public function run(): void
    {
        SectionCourse::factory(20)->create();
    }
}
