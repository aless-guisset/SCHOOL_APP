<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SchoolSeeder::class,
            UserSeeder::class,
            UserSchoolRoleSeeder::class,
            ClassroomSeeder::class,
            CourseSeeder::class,
            SectionSeeder::class,
            SectionUserSeeder::class,
            SectionCourseSeeder::class,
            SubjectSeeder::class,
            ScheduleSeeder::class,
            LessonSeeder::class,
            TimesheetSeeder::class,
            TranslationSeeder::class,
        ]);
    }
}
