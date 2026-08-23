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

function makeGradesSchool(): School
{
    return School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradesRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeGradesUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Élève inscrit dans une section, retourne [SectionUserSchoolRole, Subject]. */
function makeGradesStudentAndSubject(School $school): array
{
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $studentUsr = makeGradesUsr($school, makeGradesRole('ELEVE', 'Élève'));
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return [$sectionUser, $subject, $studentUsr];
}

test('a power user can enter a grade for a student', function () {
    $school = makeGradesSchool();
    $powerUser = makeGradesUsr($school, makeGradesRole('POWER', 'Power User'))->user;
    [$student, $subject] = makeGradesStudentAndSubject($school);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'Trimestre 1',
            'grade' => 15.5,
        ])
        ->assertRedirect('/grades');

    expect(Grade::where('section_user_id', $student->id)->where('subject_id', $subject->id)->first()?->grade)->toBe(15.5);
});

test('a teacher can enter a grade', function () {
    $school = makeGradesSchool();
    $teacher = makeGradesUsr($school, makeGradesRole('PROF', 'Professeur'))->user;
    [$student, $subject] = makeGradesStudentAndSubject($school);

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'Trimestre 1',
            'grade' => 20,
        ])
        ->assertRedirect('/grades');

    expect(Grade::where('section_user_id', $student->id)->where('subject_id', $subject->id)->first()?->grade)->toBe(20.0);
});

test('an administrateur cannot enter a grade', function () {
    $school = makeGradesSchool();
    $admin = makeGradesUsr($school, makeGradesRole('ADMIN', 'Administrateur'))->user;
    [$student, $subject] = makeGradesStudentAndSubject($school);

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'Trimestre 1',
            'grade' => 20,
        ])
        ->assertForbidden();
});

test('a grade above 20 is rejected', function () {
    $school = makeGradesSchool();
    $powerUser = makeGradesUsr($school, makeGradesRole('POWER', 'Power User'))->user;
    [$student, $subject] = makeGradesStudentAndSubject($school);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'Trimestre 1',
            'grade' => 25,
        ])
        ->assertSessionHasErrors('grade');
});

test('entering a grade for a subject in another school is rejected', function () {
    $schoolA = makeGradesSchool();
    $schoolB = makeGradesSchool();
    $powerUserA = makeGradesUsr($schoolA, makeGradesRole('POWER', 'Power User'))->user;
    [$studentA] = makeGradesStudentAndSubject($schoolA);
    [, $subjectB] = makeGradesStudentAndSubject($schoolB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/grades', [
            'section_user_id' => $studentA->id,
            'subject_id' => $subjectB->id,
            'period' => 'Trimestre 1',
            'grade' => 12,
        ])
        ->assertSessionHasErrors('subject_id');
});

test('a student only sees their own grades in the index', function () {
    $school = makeGradesSchool();
    [$studentA, $subjectA, $studentAUsr] = makeGradesStudentAndSubject($school);
    [$studentB, $subjectB] = makeGradesStudentAndSubject($school);

    Grade::create([
        'section_user_id' => $studentA->id, 'subject_id' => $subjectA->id,
        'period' => 'Trimestre 1', 'grade' => 14, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    Grade::create([
        'section_user_id' => $studentB->id, 'subject_id' => $subjectB->id,
        'period' => 'Trimestre 1', 'grade' => 8, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($studentAUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades')
        ->assertInertia(fn ($page) => $page
            ->has('grades', 1)
            ->where('grades.0.section_user_id', $studentA->id)
        );
});

test('a power user sees every students grades in the index', function () {
    $school = makeGradesSchool();
    $powerUser = makeGradesUsr($school, makeGradesRole('POWER', 'Power User'))->user;
    [$studentA, $subjectA] = makeGradesStudentAndSubject($school);
    [$studentB, $subjectB] = makeGradesStudentAndSubject($school);

    Grade::create([
        'section_user_id' => $studentA->id, 'subject_id' => $subjectA->id,
        'period' => 'Trimestre 1', 'grade' => 14, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    Grade::create([
        'section_user_id' => $studentB->id, 'subject_id' => $subjectB->id,
        'period' => 'Trimestre 1', 'grade' => 8, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades')
        ->assertInertia(fn ($page) => $page->has('grades', 2));
});

test('a teacher sees every students grades in the index', function () {
    $school = makeGradesSchool();
    $teacher = makeGradesUsr($school, makeGradesRole('PROF', 'Professeur'))->user;
    [$studentA, $subjectA] = makeGradesStudentAndSubject($school);
    [$studentB, $subjectB] = makeGradesStudentAndSubject($school);

    Grade::create([
        'section_user_id' => $studentA->id, 'subject_id' => $subjectA->id,
        'period' => 'Trimestre 1', 'grade' => 14, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    Grade::create([
        'section_user_id' => $studentB->id, 'subject_id' => $subjectB->id,
        'period' => 'Trimestre 1', 'grade' => 8, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades')
        ->assertInertia(fn ($page) => $page->has('grades', 2));
});

test('a student can download their own bulletin pdf', function () {
    $school = makeGradesSchool();
    [$student, $subject, $studentUsr] = makeGradesStudentAndSubject($school);

    Grade::create([
        'section_user_id' => $student->id, 'subject_id' => $subject->id,
        'period' => 'Trimestre 1', 'grade' => 16, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $response = $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$student->id}");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('a student cannot download another students bulletin', function () {
    $school = makeGradesSchool();
    [$studentA, , $studentAUsr] = makeGradesStudentAndSubject($school);
    [$studentB] = makeGradesStudentAndSubject($school);

    $this->actingAs($studentAUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$studentB->id}")
        ->assertForbidden();
});

test('a power user can download any students bulletin in their school', function () {
    $school = makeGradesSchool();
    $powerUser = makeGradesUsr($school, makeGradesRole('POWER', 'Power User'))->user;
    [$student] = makeGradesStudentAndSubject($school);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$student->id}")
        ->assertOk();
});

test('downloading a bulletin for a student from another school 404s', function () {
    $schoolA = makeGradesSchool();
    $schoolB = makeGradesSchool();
    $powerUserA = makeGradesUsr($schoolA, makeGradesRole('POWER', 'Power User'))->user;
    [$studentB] = makeGradesStudentAndSubject($schoolB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/grades/bulletin/{$studentB->id}")
        ->assertNotFound();
});
