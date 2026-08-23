<?php

use App\Models\Course;
use App\Models\Grade;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserSchoolRole;

test('a grade defaults to max_grade 20.00 and can store a wider scale', function () {
    $school = School::create(['name' => 'École Notes', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Maths', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $student = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $default = Grade::create(['section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 15, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    expect($default->fresh()->max_grade)->toBe(20.0);

    $wide = Grade::create(['section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => 'T2', 'grade' => 843.5, 'max_grade' => 1000, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    expect($wide->fresh()->grade)->toBe(843.5)
        ->and($wide->fresh()->max_grade)->toBe(1000.0);
});
