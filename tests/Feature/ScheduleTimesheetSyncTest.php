<?php

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Services\ScheduleTimesheetSync;
use Carbon\Carbon;

/** Construit une école + un Schedule "complet" (prof/salle/matière définis), un lundi de la semaine. */
function makeSyncSchedule(?string $yearEndDate = null, int $dayOfWeek = 1): array
{
    $school = School::create([
        'name' => 'École Sync '.uniqid(), 'status' => 'A', 'is_active' => true,
        'year_end_date' => $yearEndDate, 'created_by' => 1,
    ]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherUser = User::factory()->create();
    $usr = UserSchoolRole::create(['user_id' => $teacherUser->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    // Créé sans observer (ce test cible le service directement, pas Schedule::create()).
    $schedule = Schedule::create([
        'section_course_id' => $sectionCourse->id,
        'user_school_role_id' => $usr->id, 'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'name' => 'Créneau', 'day_of_week' => $dayOfWeek, 'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return compact('school', 'schedule', 'usr', 'classroom', 'subject');
}

test('sync generates one timesheet per week from today to year_end_date', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(3)->toDateString();

    ['schedule' => $schedule] = makeSyncSchedule($yearEnd, $dow);

    $result = (new ScheduleTimesheetSync())->sync($schedule->fresh());

    // Semaines : aujourd'hui, +1, +2, +3 (toutes ≤ year_end) = 4 occurrences.
    expect($result['created'])->toBe(4);
    expect(Timesheet::where('schedule_id', $schedule->id)->count())->toBe(4);
});

test('sync does nothing for a schedule missing teacher, subject or classroom', function () {
    $today = Carbon::today();
    ['school' => $school, 'schedule' => $schedule] = makeSyncSchedule($today->copy()->addMonth()->toDateString(), $today->dayOfWeekIso);

    $schedule->update(['classroom_id' => null]);

    $result = (new ScheduleTimesheetSync())->sync($schedule->fresh());

    expect($result)->toBe(['created' => 0, 'skipped_conflicts' => 0]);
    expect(Timesheet::where('schedule_id', $schedule->id)->count())->toBe(0);
});

test('sync does nothing when the school has no year_end_date', function () {
    $today = Carbon::today();
    ['schedule' => $schedule] = makeSyncSchedule(null, $today->dayOfWeekIso);

    $result = (new ScheduleTimesheetSync())->sync($schedule->fresh());

    expect($result)->toBe(['created' => 0, 'skipped_conflicts' => 0]);
});

test('sync preserves customized future timesheets but replaces standard ones', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(2)->toDateString();
    ['schedule' => $schedule, 'usr' => $usr, 'classroom' => $classroom, 'subject' => $subject] = makeSyncSchedule($yearEnd, $dow);

    $sync = new ScheduleTimesheetSync();
    $sync->sync($schedule->fresh()); // génère les 3 occurrences (aujourd'hui, +1, +2 semaines)

    $nextWeekDate = $today->copy()->addWeek()->toDateString();
    $customized = Timesheet::where('schedule_id', $schedule->id)->where('date', $nextWeekDate)->firstOrFail();
    $customized->update(['is_customized' => true, 'hours_done' => 99]);

    // Un changement de prof déclenche une resynchronisation.
    $newTeacherUser = User::factory()->create();
    $newUsr = UserSchoolRole::create(['user_id' => $newTeacherUser->id, 'school_id' => $schedule->sectionCourse->course->school_id, 'role_id' => $usr->role_id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $schedule->update(['user_school_role_id' => $newUsr->id]);

    $sync->sync($schedule->fresh());

    // La séance personnalisée survit intacte.
    expect($customized->fresh()->hours_done)->toBe(99);
    expect($customized->fresh()->is_customized)->toBeTrue();

    // Les séances standard (aujourd'hui, +2 semaines) ont le nouveau prof.
    $today = Timesheet::where('schedule_id', $schedule->id)->where('date', Carbon::today()->toDateString())->first();
    expect($today->user_school_role_id)->toBe($newUsr->id);
});

test('deleteFutureStandard removes only non-customized future timesheets', function () {
    $today = Carbon::today();
    ['schedule' => $schedule] = makeSyncSchedule($today->copy()->addWeeks(2)->toDateString(), $today->dayOfWeekIso);

    $sync = new ScheduleTimesheetSync();
    $sync->sync($schedule->fresh());

    $futureDate = $today->copy()->addWeek()->toDateString();
    Timesheet::where('schedule_id', $schedule->id)->where('date', $futureDate)->update(['is_customized' => true]);

    $deleted = $sync->deleteFutureStandard($schedule->fresh());

    expect($deleted)->toBe(2); // aujourd'hui + 2 semaines, pas la personnalisée
    expect(Timesheet::where('schedule_id', $schedule->id)->count())->toBe(1);
    expect(Timesheet::where('schedule_id', $schedule->id)->first()->is_customized)->toBeTrue();
});

test('sync skips a date where a conflict exists without blocking other occurrences', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(2)->toDateString();
    ['school' => $school, 'schedule' => $schedule, 'usr' => $usr, 'classroom' => $classroom, 'subject' => $subject] = makeSyncSchedule($yearEnd, $dow);

    // Le même prof est déjà occupé ailleurs, même horaire, la semaine +1.
    $conflictDate = $today->copy()->addWeek();
    $otherSection = Section::create(['school_id' => $school->id, 'name' => 'Autre classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherCourse = Course::create(['school_id' => $school->id, 'name' => 'Autre cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionUser = SectionUserSchoolRole::create(['section_id' => $otherSection->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionCourse = SectionCourse::create(['section_user_id' => $otherSectionUser->id, 'course_id' => $otherCourse->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'AutreSC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSchedule = Schedule::create(['section_course_id' => $otherSectionCourse->id, 'name' => 'Conflit', 'day_of_week' => $dow, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    Timesheet::create(['user_school_role_id' => $usr->id, 'schedule_id' => $otherSchedule->id, 'subject_id' => $subject->id, 'classroom_id' => $classroom->id, 'date' => $conflictDate->toDateString(), 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $result = (new ScheduleTimesheetSync())->sync($schedule->fresh());

    expect($result['skipped_conflicts'])->toBe(1);
    expect($result['created'])->toBe(2); // aujourd'hui + 2 semaines
    expect(Timesheet::where('schedule_id', $schedule->id)->where('date', $conflictDate->toDateString())->exists())->toBeFalse();
});

test('sync never touches past timesheets', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(2)->toDateString();
    ['schedule' => $schedule, 'usr' => $usr, 'classroom' => $classroom, 'subject' => $subject] = makeSyncSchedule($yearEnd, $dow);

    // Manually insert a past timesheet with a distinctive hours_done value.
    $pastDate = $today->copy()->subWeek()->toDateString();
    $pastTimesheet = Timesheet::create([
        'user_school_role_id' => $usr->id,
        'schedule_id' => $schedule->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
        'date' => $pastDate,
        'hours_done' => 2,
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $pastTimesheetId = $pastTimesheet->id;

    $result = (new ScheduleTimesheetSync())->sync($schedule->fresh());

    // The past timesheet should never be touched.
    $pastTimesheetAfter = Timesheet::find($pastTimesheetId);
    expect($pastTimesheetAfter)->not->toBeNull();
    expect($pastTimesheetAfter->id)->toBe($pastTimesheetId);
    expect($pastTimesheetAfter->hours_done)->toBe(2);
    expect($pastTimesheetAfter->date)->toBe($pastDate);
});

test('a manually-created future timesheet without is_generated survives sync', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(2)->toDateString();
    ['schedule' => $schedule, 'usr' => $usr, 'classroom' => $classroom, 'subject' => $subject] = makeSyncSchedule($yearEnd, $dow);

    // A human manually creates a future timesheet for this schedule's slot,
    // never going through the generator: is_generated stays at its false
    // default, even though it otherwise "looks" standard (is_customized
    // false, hours_done 0).
    $futureDate = $today->copy()->addWeek()->toDateString();
    $manual = Timesheet::create([
        'user_school_role_id' => $usr->id,
        'schedule_id' => $schedule->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
        'date' => $futureDate,
        'hours_done' => 0,
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $manualId = $manual->id;

    // Not passed to create(), so it takes the column's DB default (false) —
    // fetch fresh from the DB rather than reading the in-memory attribute.
    expect($manual->fresh()->is_generated)->toBeFalse();

    (new ScheduleTimesheetSync())->sync($schedule->fresh());

    $manualAfter = Timesheet::find($manualId);
    expect($manualAfter)->not->toBeNull();
    expect($manualAfter->id)->toBe($manualId);
    expect($manualAfter->date)->toBe($futureDate);
});

test('a generated timesheet with hours_done > 0 survives a later sync', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(2)->toDateString();
    ['schedule' => $schedule] = makeSyncSchedule($yearEnd, $dow);

    $sync = new ScheduleTimesheetSync();
    $sync->sync($schedule->fresh());

    $futureDate = $today->copy()->addWeek()->toDateString();
    $generated = Timesheet::where('schedule_id', $schedule->id)->where('date', $futureDate)->firstOrFail();
    expect($generated->is_generated)->toBeTrue();

    // A teacher pre-logs some hours on the future session directly (not via the controller).
    $generated->update(['hours_done' => 2]);
    $generatedId = $generated->id;

    $sync->sync($schedule->fresh());

    $after = Timesheet::find($generatedId);
    expect($after)->not->toBeNull();
    expect($after->id)->toBe($generatedId);
    expect($after->hours_done)->toBe(2);
});

test('a generated timesheet with a recorded attendance survives sync', function () {
    $today = Carbon::today();
    $dow = $today->dayOfWeekIso;
    $yearEnd = $today->copy()->addWeeks(2)->toDateString();
    ['school' => $school, 'schedule' => $schedule] = makeSyncSchedule($yearEnd, $dow);

    $sync = new ScheduleTimesheetSync();
    $sync->sync($schedule->fresh());

    $futureDate = $today->copy()->addWeek()->toDateString();
    $generated = Timesheet::where('schedule_id', $schedule->id)->where('date', $futureDate)->firstOrFail();
    $generatedId = $generated->id;

    $eleveRole = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentUser = User::factory()->create();
    $studentUsr = UserSchoolRole::create(['user_id' => $studentUser->id, 'school_id' => $school->id, 'role_id' => $eleveRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentSection = Section::create(['school_id' => $school->id, 'name' => 'Classe Attendance', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentSectionUser = SectionUserSchoolRole::create(['section_id' => $studentSection->id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $attendance = Attendance::create([
        'timesheet_id' => $generatedId,
        'section_user_id' => $studentSectionUser->id,
        'is_present' => true,
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);

    $sync->sync($schedule->fresh());

    $after = Timesheet::find($generatedId);
    expect($after)->not->toBeNull();
    expect($after->id)->toBe($generatedId);
    expect($attendance->fresh()->timesheet_id)->toBe($generatedId);
});
