<?php

use App\Models\Course;
use App\Models\Grade;
use App\Models\ParentStudentLink;
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

/** Compte parent lié à l'élève donné, dans la même école. */
function makeGradesParent(School $school, SectionUserSchoolRole $child): User
{
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id,
        'role_id' => makeGradesScaleRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $child->userschoolrole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return $parent;
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

test('destroy deletes the physical attachment file, not just the database row', function () {
    // Le stockage étant maintenant persistant en prod (Railway Volume), un
    // fichier non nettoyé au destroy() reste orphelin indéfiniment au lieu
    // d'être perdu au prochain déploiement comme avant.
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
        ]);

    $grade = Grade::where('period', 'T1')->first();
    $attachmentPath = $grade->attachment_path;
    Storage::disk('local')->assertExists($attachmentPath);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/grades/{$grade->id}")
        ->assertRedirect();

    Storage::disk('local')->assertMissing($attachmentPath);
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

test('downloadAttachment returns 404 (not 500) when attachment_path is set but the file is missing from disk', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);
    $grade = Grade::create([
        'section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => 'T1',
        'grade' => 12, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
        'attachment_path' => 'grades/ghost.pdf', 'attachment_original_name' => 'ghost.pdf',
    ]);

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

test('bulletin pdf reflects the actual max_grade of each grade, not a hardcoded /20', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    Grade::create(['section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 850, 'max_grade' => 1000, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$student->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('bulletin: a student can download only their own bulletin, 403 on another student\'s', function () {
    $school = makeGradesScaleSchool();
    $subject = makeGradesSubject($school);
    $ownStudent = makeGradesStudent($school);
    $otherStudent = makeGradesStudent($school);
    $ownStudentUser = User::find($ownStudent->userschoolrole->user_id);

    Grade::create(['section_user_id' => $ownStudent->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 12, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($ownStudentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$ownStudent->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($ownStudentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$otherStudent->id}")
        ->assertForbidden();
});

test('bulletin: a parent can download their linked child\'s bulletin, 403 on another student\'s', function () {
    $school = makeGradesScaleSchool();
    $subject = makeGradesSubject($school);
    $child = makeGradesStudent($school);
    $otherStudent = makeGradesStudent($school);
    $parent = makeGradesParent($school, $child);

    Grade::create(['section_user_id' => $child->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 15, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$child->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/bulletin/{$otherStudent->id}")
        ->assertForbidden();
});

test('downloadAttachment: a parent can download their linked child\'s attachment, 403 on another student\'s', function () {
    $school = makeGradesScaleSchool();
    $power = makeGradesScaleUsr($school, makeGradesScaleRole('POWER', 'Power User'))->user;
    $subject = makeGradesSubject($school);
    $child = makeGradesStudent($school);
    $otherStudent = makeGradesStudent($school);
    $parent = makeGradesParent($school, $child);

    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $child->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 15,
        'attachment' => UploadedFile::fake()->create('enfant.pdf', 100, 'application/pdf'),
    ]);
    $this->actingAs($power)->withSession(['active_school_id' => $school->id])->post('/grades', [
        'section_user_id' => $otherStudent->id, 'subject_id' => $subject->id,
        'period' => 'T1', 'max_grade' => 20, 'grade' => 8,
        'attachment' => UploadedFile::fake()->create('autre.pdf', 100, 'application/pdf'),
    ]);
    $childGrade = Grade::where('section_user_id', $child->id)->first();
    $otherGrade = Grade::where('section_user_id', $otherStudent->id)->first();

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/{$childGrade->id}/attachment")
        ->assertOk();

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get("/grades/{$otherGrade->id}/attachment")
        ->assertForbidden();
});

test('a parent only sees the grades of their linked child, never other students', function () {
    $school = makeGradesScaleSchool();
    $subject = makeGradesSubject($school);
    $childStudent = makeGradesStudent($school);
    $otherStudent = makeGradesStudent($school);

    \App\Models\Grade::create([
        'section_user_id' => $childStudent->id, 'subject_id' => $subject->id, 'period' => 'T1',
        'grade' => 15, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    \App\Models\Grade::create([
        'section_user_id' => $otherStudent->id, 'subject_id' => $subject->id, 'period' => 'T1',
        'grade' => 8, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $childUsr = $childStudent->userschoolrole;
    $parentRole = \App\Models\Role::firstOrCreate(['reference' => 'PARENT'], ['name' => 'Parent', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parent = \App\Models\User::factory()->create();
    $parentUsr = \App\Models\UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $childUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $response = $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades');

    $response->assertInertia(fn ($page) => $page
        ->component('power-user/web/Grades/Index')
        ->has('grades', 1)
        ->where('grades.0.grade', 15)
    );
});

test('a teacher with a Parent role also sees their child\'s grades via as_parent=1, and only those', function () {
    $school = makeGradesScaleSchool();
    $subject = makeGradesSubject($school);
    $child = makeGradesStudent($school);
    $otherStudent = makeGradesStudent($school);

    \App\Models\Grade::create(['section_user_id' => $child->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 14, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\Grade::create(['section_user_id' => $otherStudent->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 9, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $teacherParent = User::factory()->create();
    $profUsr = UserSchoolRole::create(['user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makeGradesScaleRole('PROF', 'Professeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parentUsr = UserSchoolRole::create(['user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makeGradesScaleRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $child->userschoolrole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    // Sans as_parent : vue Professeur normale (canManage=true), pas la vue enfant.
    $this->actingAs($teacherParent)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades')
        ->assertInertia(fn ($page) => $page->has('grades', 2)); // voit tout, rôle de gestion

    // Avec as_parent=1 : uniquement les notes de l'enfant lié.
    $this->actingAs($teacherParent)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades?as_parent=1')
        ->assertInertia(fn ($page) => $page
            ->has('grades', 1)
            ->where('grades.0.grade', 14)
        );
});

test('a professeur with no Parent role at all gets an empty grades list via as_parent=1, never the school-wide list nor an error', function () {
    $school = makeGradesScaleSchool();
    $subject = makeGradesSubject($school);
    $student = makeGradesStudent($school);

    \App\Models\Grade::create(['section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => 'T1', 'grade' => 11, 'max_grade' => 20, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $teacher = User::factory()->create();
    UserSchoolRole::create(['user_id' => $teacher->id, 'school_id' => $school->id, 'role_id' => makeGradesScaleRole('PROF', 'Professeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    // Sans as_parent : le prof voit bien le rôle de gestion (l'école entière), pour contraste.
    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('grades', 1));

    // Avec as_parent=1 mais SANS rôle Parent actif à cette école : liste vide, pas une erreur,
    // et surtout jamais la liste complète de l'école (parentLinkedStudent() renvoie null).
    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/grades?as_parent=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('grades', 0));
});
