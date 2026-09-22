<?php

use App\Concerns\ResolvesCourseTeacher;
use App\Models\Course;
use App\Models\CourseResource;
use App\Models\Devoir;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeSCSchool(): School
{
    return School::create(['name' => 'École SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSCRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSCUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

/**
 * Section + section_user (élève placeholder, comme en vrai — voir
 * DemoSchoolSeeder::makeSectionCourses()) + SectionCourse, affecté au
 * professeur via un Schedule (affectation par défaut). Retourne le SectionCourse.
 */
function makeSCFixture(School $school, UserSchoolRole $teacherUsr, string $sectionName = 'Classe A'): SectionCourse
{
    $eleveRole = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $placeholderStudentUsr = makeSCUsr($school, $eleveRole);
    $section = Section::create(['school_id' => $school->id, 'name' => $sectionName, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $placeholderStudentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => "Cours {$sectionName}", 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => "SC {$sectionName}", 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    Schedule::create([
        'section_course_id' => $sectionCourse->id, 'user_school_role_id' => $teacherUsr->id,
        'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return $sectionCourse;
}

test('Devoir belongs to a SectionCourse and is scoped to the active school', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sectionCourse = makeSCFixture($school, $teacherUsr);

    $devoir = Devoir::create([
        'section_course_id' => $sectionCourse->id, 'title' => 'Exercices chapitre 3',
        'description' => 'Faire les exos 1 à 5', 'due_date' => '2026-10-01',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($devoir->sectionCourse->id)->toBe($sectionCourse->id);

    session(['active_school_id' => $school->id]);
    expect(Devoir::find($devoir->id))->not->toBeNull();

    $otherSchool = makeSCSchool();
    session(['active_school_id' => $otherSchool->id]);
    expect((new Devoir)->resolveRouteBinding($devoir->id))->toBeNull();
});

test('CourseResource belongs to a SectionCourse and is scoped to the active school', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sectionCourse = makeSCFixture($school, $teacherUsr);

    $resource = CourseResource::create([
        'section_course_id' => $sectionCourse->id, 'title' => 'Support de cours PDF',
        'type' => 'link', 'url' => 'https://example.com/support.pdf',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($resource->sectionCourse->id)->toBe($sectionCourse->id);

    session(['active_school_id' => $school->id]);
    expect(CourseResource::find($resource->id))->not->toBeNull();

    $otherSchool = makeSCSchool();
    session(['active_school_id' => $otherSchool->id]);
    expect((new CourseResource)->resolveRouteBinding($resource->id))->toBeNull();
});

test('professorTeachesSectionCourse is true via the default assignment (schedules.user_school_role_id)', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sectionCourse = makeSCFixture($school, $teacherUsr);

    $probe = new class
    {
        use ResolvesCourseTeacher;

        public function check(SectionCourse $sc, UserSchoolRole $usr): bool
        {
            return $this->professorTeachesSectionCourse($sc, $usr);
        }
    };

    expect($probe->check($sectionCourse, $teacherUsr))->toBeTrue();
});

test('professorTeachesSectionCourse is true via the legacy convention (section_user_id pointing directly to the professor)', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe Legacy', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours Legacy', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC Legacy', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $probe = new class
    {
        use ResolvesCourseTeacher;

        public function check(SectionCourse $sc, UserSchoolRole $usr): bool
        {
            return $this->professorTeachesSectionCourse($sc, $usr);
        }
    };

    expect($probe->check($sectionCourse, $teacherUsr))->toBeTrue();
});

test('professorTeachesSectionCourse is false for a colleague', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $colleagueUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sectionCourse = makeSCFixture($school, $teacherUsr);

    $probe = new class
    {
        use ResolvesCourseTeacher;

        public function check(SectionCourse $sc, UserSchoolRole $usr): bool
        {
            return $this->professorTeachesSectionCourse($sc, $usr);
        }
    };

    expect($probe->check($sectionCourse, $colleagueUsr))->toBeFalse();
});

test('index scopes to the professors own SectionCourse only', function () {
    $school = makeSCSchool();
    $teacherA = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $teacherB = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $scA = makeSCFixture($school, $teacherA, 'Classe A');
    makeSCFixture($school, $teacherB, 'Classe B');

    $this->actingAs($teacherA->user)
        ->withSession(['active_school_id' => $school->id])
        ->get('/section-courses')
        ->assertInertia(fn ($page) => $page
            ->has('sectionCourses.data', 1)
            ->where('sectionCourses.data.0.id', $scA->id)
        );
});

test('index scopes to the students own section only', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $scA = makeSCFixture($school, $teacherUsr, 'Classe A');
    makeSCFixture($school, $teacherUsr, 'Classe B');

    $studentUsr = makeSCUsr($school, makeSCRole('ELEVE', 'Élève'));
    $sectionAId = $scA->sectionUser->section_id;
    SectionUserSchoolRole::create(['section_id' => $sectionAId, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get('/section-courses')
        ->assertInertia(fn ($page) => $page
            ->has('sectionCourses.data', 1)
            ->where('sectionCourses.data.0.id', $scA->id)
        );
});

test('index shows every SectionCourse to a Power User', function () {
    $school = makeSCSchool();
    $teacherA = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $teacherB = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    makeSCFixture($school, $teacherA, 'Classe A');
    makeSCFixture($school, $teacherB, 'Classe B');
    $powerUser = makeSCUsr($school, makeSCRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/section-courses')
        ->assertInertia(fn ($page) => $page->has('sectionCourses.data', 2));
});

test('show returns 404 for a professor who does not teach this SectionCourse', function () {
    $school = makeSCSchool();
    $teacherA = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $teacherB = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $scB = makeSCFixture($school, $teacherB, 'Classe B');

    $this->actingAs($teacherA->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/section-courses/{$scB->id}")
        ->assertNotFound();
});

test('show returns 404 for a student outside the SectionCourse section', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sc = makeSCFixture($school, $teacherUsr);
    $studentUsr = makeSCUsr($school, makeSCRole('ELEVE', 'Élève'));

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/section-courses/{$sc->id}")
        ->assertNotFound();
});

test('show is accessible with can_manage_course true for the teaching professor, false for a student', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sc = makeSCFixture($school, $teacherUsr);
    $studentUsr = makeSCUsr($school, makeSCRole('ELEVE', 'Élève'));
    SectionUserSchoolRole::create(['section_id' => $sc->sectionUser->section_id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/section-courses/{$sc->id}")
        ->assertInertia(fn ($page) => $page->where('can_manage_course', true));

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/section-courses/{$sc->id}")
        ->assertInertia(fn ($page) => $page->where('can_manage_course', false));
});

test('show remains fully accessible to Power User regardless of section', function () {
    $school = makeSCSchool();
    $teacherUsr = makeSCUsr($school, makeSCRole('PROF', 'Professeur'));
    $sc = makeSCFixture($school, $teacherUsr);
    $powerUser = makeSCUsr($school, makeSCRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/section-courses/{$sc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can_manage_course', false));
});
