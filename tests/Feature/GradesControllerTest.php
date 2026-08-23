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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeGradesScaleSchool(): School
{
    return School::create(['name' => 'École Notes '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradesScaleRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradesScaleUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradesSubject(School $school, string $name = 'Maths'): Subject
{
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return Subject::create(['course_id' => $course->id, 'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeGradesStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = makeGradesScaleUsr($school, makeGradesScaleRole('ELEVE', 'Élève'));

    return SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

beforeEach(function () {
    Storage::fake('local');
});

test('store rejects a grade above max_grade', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id, 'subject_id' => $subject->id,
            'period' => 'T1', 'max_grade' => 10, 'grade' => 15,
        ])
        ->assertSessionHasErrors('grade');
});

test('store accepts a grade equal to max_grade', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id, 'subject_id' => $subject->id,
            'period' => 'T1', 'max_grade' => 10, 'grade' => 10,
        ])
        ->assertSessionDoesntHaveErrors();

    expect(Grade::where('period', 'T1')->first()->max_grade)->toBe(10.0);
});

test('store accepts a valid pdf attachment and records its path and original name', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);
    $file = UploadedFile::fake()->create('controle-t1.pdf', 500, 'application/pdf');

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id, 'subject_id' => $subject->id,
            'period' => 'T1', 'max_grade' => 20, 'grade' => 15, 'attachment' => $file,
        ])
        ->assertSessionDoesntHaveErrors();

    $grade = Grade::where('period', 'T1')->first();
    expect($grade->attachment_original_name)->toBe('controle-t1.pdf');
    Storage::disk('local')->assertExists($grade->attachment_path);
});

test('store rejects a disallowed file type', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);
    $file = UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream');

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id, 'subject_id' => $subject->id,
            'period' => 'T1', 'max_grade' => 20, 'grade' => 15, 'attachment' => $file,
        ])
        ->assertSessionHasErrors('attachment');
});

test('store rejects a file over 10MB', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);
    $file = UploadedFile::fake()->create('gros.pdf', 10241, 'application/pdf');

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $student->id, 'subject_id' => $subject->id,
            'period' => 'T1', 'max_grade' => 20, 'grade' => 15, 'attachment' => $file,
        ])
        ->assertSessionHasErrors('attachment');
});

test('re-submitting the same student/subject/period with a new file replaces the old physical file', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $student->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 12,
        'attachment' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
    ]);
    $oldPath = Grade::where('period', 'T1')->first()->attachment_path;

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $student->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 14,
        'attachment' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
    ]);
    $grade = Grade::where('period', 'T1')->first();

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($grade->attachment_path);
    expect($grade->attachment_original_name)->toBe('v2.pdf')
        ->and($grade->grade)->toBe(14.0);
});

test('re-submitting the same key without a file in the request keeps the existing attachment', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $student->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 12,
        'attachment' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
    ]);
    $originalPath = Grade::where('period', 'T1')->first()->attachment_path;

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $student->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 16,
    ]);
    $grade = Grade::where('period', 'T1')->first();

    expect($grade->attachment_path)->toBe($originalPath)
        ->and($grade->grade)->toBe(16.0);
    Storage::disk('local')->assertExists($originalPath);
});

test('downloadAttachment returns 404 when the grade has no attachment', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);
    $grade = Grade::create(['section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 12, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/{$grade->id}/attachment")
        ->assertNotFound();
});

test('downloadAttachment: staff can download any grade attachment in the school', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $student->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 12,
        'attachment' => UploadedFile::fake()->create('bulletin.pdf', 100, 'application/pdf'),
    ]);
    $grade = Grade::where('period', 'T1')->first();

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/{$grade->id}/attachment")
        ->assertOk();
});

test('downloadAttachment: a student can download only their own grade attachment, 403 on another student\'s', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $ownStudent = makeGradesStudent($school);
    $otherStudent = makeGradesStudent($school);
    $ownStudentUser = User::find($ownStudent->userschoolrole->user_id);

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $ownStudent->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 12,
        'attachment' => UploadedFile::fake()->create('own.pdf', 100, 'application/pdf'),
    ]);
    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $otherStudent->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 14,
        'attachment' => UploadedFile::fake()->create('other.pdf', 100, 'application/pdf'),
    ]);
    $ownGrade = Grade::where('section_user_id', $ownStudent->id)->first();
    $otherGrade = Grade::where('section_user_id', $otherStudent->id)->first();

    $this->actingAs($ownStudentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/{$ownGrade->id}/attachment")
        ->assertOk();

    $this->actingAs($ownStudentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/{$otherGrade->id}/attachment")
        ->assertForbidden();
});

test('index filters by subject_id for both staff and a student', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $maths = makeGradesSubject($school, 'Maths');
    $francais = makeGradesSubject($school, 'Français');
    $student = makeGradesStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);

    Grade::create(['section_user_id' => $student->id, 'subject_id' => $maths->id, 'period' => 'T1', 'grade' => 12, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    Grade::create(['section_user_id' => $student->id, 'subject_id' => $francais->id, 'period' => 'T1', 'grade' => 14, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades?subject_id='.$maths->id)
        ->assertInertia(fn ($page) => $page->has('grades', 1)->where('grades.0.subject_id', $maths->id));

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades?subject_id='.$maths->id)
        ->assertInertia(fn ($page) => $page->has('grades', 1)->where('grades.0.subject_id', $maths->id));
});
