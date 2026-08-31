<?php

use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\GradeAddedNotification;
use Illuminate\Support\Facades\Notification;

function makeGradeNotifSchool(): School
{
    return School::create(['name' => 'École GradeNotif '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradeNotifRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradeNotifUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeGradeNotifSubject(School $school): Subject
{
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return Subject::create(['course_id' => $course->id, 'name' => 'Maths', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradeNotifStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = makeGradeNotifUsr($school, makeGradeNotifRole('ELEVE', 'Élève'));

    return SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function linkGradeNotifParent(SectionUserSchoolRole $student, User $parent): void
{
    // Un parent n'a qu'une seule UserSchoolRole par école (contrainte unique
    // user_id+school_id+role_id) : on la réutilise si elle existe déjà, pour
    // pouvoir lier le même parent à plusieurs enfants via plusieurs
    // ParentStudentLink pointant vers cette même ligne.
    $parentUsr = UserSchoolRole::firstOrCreate(
        [
            'user_id' => $parent->id, 'school_id' => $student->section->school_id,
            'role_id' => makeGradeNotifRole('PARENT', 'Parent')->id,
        ],
        ['status' => 'A', 'is_active' => true, 'created_by' => 1],
    );
    \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->userschoolrole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('creating a grade notifies the linked parent', function () {
    Notification::fake();

    $school = makeGradeNotifSchool();
    $subject = makeGradeNotifSubject($school);
    $student = makeGradeNotifStudent($school);
    $parent = User::factory()->create();
    linkGradeNotifParent($student, $parent);

    $powerUser = makeGradeNotifUsr($school, makeGradeNotifRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'T1',
            'max_grade' => 20,
            'grade' => 15,
        ])
        ->assertRedirect();

    Notification::assertSentTo($parent, GradeAddedNotification::class);
});

test('creating a grade does not notify a parent of a different student', function () {
    Notification::fake();

    $school = makeGradeNotifSchool();
    $subject = makeGradeNotifSubject($school);
    $student = makeGradeNotifStudent($school);
    $otherStudent = makeGradeNotifStudent($school);
    $otherParent = User::factory()->create();
    linkGradeNotifParent($otherStudent, $otherParent);

    $powerUser = makeGradeNotifUsr($school, makeGradeNotifRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'T1',
            'max_grade' => 20,
            'grade' => 15,
        ])
        ->assertRedirect();

    Notification::assertNotSentTo($otherParent, GradeAddedNotification::class);
});

test('creating a grade for a student with no linked parent does not error', function () {
    Notification::fake();

    $school = makeGradeNotifSchool();
    $subject = makeGradeNotifSubject($school);
    $student = makeGradeNotifStudent($school);
    $powerUser = makeGradeNotifUsr($school, makeGradeNotifRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id,
            'subject_id' => $subject->id,
            'period' => 'T1',
            'max_grade' => 20,
            'grade' => 15,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('a parent linked to two children only gets notified for the child whose grade changed', function () {
    Notification::fake();

    $school = makeGradeNotifSchool();
    $subject = makeGradeNotifSubject($school);
    $studentA = makeGradeNotifStudent($school);
    $studentB = makeGradeNotifStudent($school);
    $parent = User::factory()->create();
    linkGradeNotifParent($studentA, $parent);
    linkGradeNotifParent($studentB, $parent);

    $powerUser = makeGradeNotifUsr($school, makeGradeNotifRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $studentA->id,
            'subject_id' => $subject->id,
            'period' => 'T1',
            'max_grade' => 20,
            'grade' => 15,
        ])
        ->assertRedirect();

    // Une seule note créée pour un seul des deux enfants : exactement une
    // notification, jamais une par enfant lié (pas de notification groupée
    // ni de notification fantôme sur l'enfant dont la note n'a pas changé).
    Notification::assertSentToTimes($parent, GradeAddedNotification::class, 1);
});
