<?php

use App\Models\Course;
use App\Models\CourseResource;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeResSchool(): School
{
    return School::create(['name' => 'École Ressources', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeResRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeResUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeResSectionCourse(School $school, UserSchoolRole $teacherUsr): SectionCourse
{
    $eleveRole = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $placeholderStudentUsr = makeResUsr($school, $eleveRole);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $placeholderStudentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Maths', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    Schedule::create(['section_course_id' => $sectionCourse->id, 'user_school_role_id' => $teacherUsr->id, 'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return $sectionCourse;
}

beforeEach(function () {
    Storage::fake('local');
});

test('the teaching professor can add a link resource', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", [
            'title' => 'Support en ligne', 'type' => 'link', 'url' => 'https://example.com/support',
        ])
        ->assertRedirect();

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();
    expect($resource->type)->toBe('link');
    expect($resource->url)->toBe('https://example.com/support');
});

test('the teaching professor can upload a file resource', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $file = UploadedFile::fake()->create('support.pdf', 500, 'application/pdf');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", [
            'title' => 'Support PDF', 'type' => 'file', 'attachment' => $file,
        ])
        ->assertRedirect();

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();
    expect($resource->attachment_original_name)->toBe('support.pdf');
    Storage::disk('local')->assertExists($resource->attachment_path);
});

test('link resource requires a url, file resource requires a file', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Sans url', 'type' => 'link'])
        ->assertSessionHasErrors('url');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Sans fichier', 'type' => 'file'])
        ->assertSessionHasErrors('attachment');
});

test('a colleague professor cannot add a resource to another teachers course', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $colleagueUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);

    $this->actingAs($colleagueUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Intrusion', 'type' => 'link', 'url' => 'https://evil.example'])
        ->assertForbidden();

    expect(CourseResource::where('section_course_id', $sc->id)->count())->toBe(0);
});

test('the teaching professor can delete a resource and its file is removed from disk', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $file = UploadedFile::fake()->create('support.pdf', 500, 'application/pdf');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Support', 'type' => 'file', 'attachment' => $file]);

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();
    $path = $resource->attachment_path;

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/course-resources/{$resource->id}")
        ->assertRedirect();

    expect(CourseResource::find($resource->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('a colleague professor cannot delete a resource that is not theirs', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $colleagueUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $resource = CourseResource::create(['section_course_id' => $sc->id, 'title' => 'Lien', 'type' => 'link', 'url' => 'https://example.com', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($colleagueUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/course-resources/{$resource->id}")
        ->assertForbidden();

    expect(CourseResource::find($resource->id))->not->toBeNull();
});

test('the teaching professor can download the file resource of their own course', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $file = UploadedFile::fake()->create('support.pdf', 500, 'application/pdf');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Support', 'type' => 'file', 'attachment' => $file]);

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/course-resources/{$resource->id}/attachment")
        ->assertOk();
});

test('a student of the resources section can download it, a student of another section cannot', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $file = UploadedFile::fake()->create('support.pdf', 500, 'application/pdf');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Support', 'type' => 'file', 'attachment' => $file]);

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();

    $inSectionStudentUsr = makeResUsr($school, makeResRole('ELEVE', 'Élève'));
    SectionUserSchoolRole::create(['section_id' => $sc->sectionUser->section_id, 'user_school_role_id' => $inSectionStudentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($inSectionStudentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/course-resources/{$resource->id}/attachment")
        ->assertOk();

    $outsideStudentUsr = makeResUsr($school, makeResRole('ELEVE', 'Élève'));

    $this->actingAs($outsideStudentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/course-resources/{$resource->id}/attachment")
        ->assertForbidden();
});

test('a colleague professor cannot download a resource of a course they do not teach', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $colleagueUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $file = UploadedFile::fake()->create('support.pdf', 500, 'application/pdf');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Support', 'type' => 'file', 'attachment' => $file]);

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();

    $this->actingAs($colleagueUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/course-resources/{$resource->id}/attachment")
        ->assertForbidden();
});

test('Power User can download any resource regardless of section', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $file = UploadedFile::fake()->create('support.pdf', 500, 'application/pdf');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/resources", ['title' => 'Support', 'type' => 'file', 'attachment' => $file]);

    $resource = CourseResource::where('section_course_id', $sc->id)->firstOrFail();
    $powerUser = makeResUsr($school, makeResRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/course-resources/{$resource->id}/attachment")
        ->assertOk();
});

test('downloading a missing file returns 404', function () {
    $school = makeResSchool();
    $teacherUsr = makeResUsr($school, makeResRole('PROF', 'Professeur'));
    $sc = makeResSectionCourse($school, $teacherUsr);
    $resource = CourseResource::create(['section_course_id' => $sc->id, 'title' => 'Lien', 'type' => 'link', 'url' => 'https://example.com', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/course-resources/{$resource->id}/attachment")
        ->assertNotFound();
});
