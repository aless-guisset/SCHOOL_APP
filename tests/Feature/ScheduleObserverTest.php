<?php

use App\Models\Timesheet;
use Carbon\Carbon;

test('creating a complete schedule automatically generates future timesheets', function () {
    $today = Carbon::today();
    // makeSyncSchedule() (défini dans ScheduleTimesheetSyncTest.php) crée déjà le
    // Schedule via Schedule::create() — l'observer, une fois enregistré, doit donc
    // avoir généré les timesheets sans appel explicite au service.
    ['schedule' => $schedule] = makeSyncSchedule($today->copy()->addWeeks(2)->toDateString(), $today->dayOfWeekIso);

    expect(Timesheet::where('schedule_id', $schedule->id)->count())->toBe(3);
});

test('updating a schedules teacher regenerates standard future timesheets with the new teacher', function () {
    $today = Carbon::today();
    ['schedule' => $schedule, 'school' => $school, 'usr' => $usr] = makeSyncSchedule($today->copy()->addWeek()->toDateString(), $today->dayOfWeekIso);

    $newTeacherUser = \App\Models\User::factory()->create();
    $newUsr = \App\Models\UserSchoolRole::create([
        'user_id' => $newTeacherUser->id, 'school_id' => $school->id, 'role_id' => $usr->role_id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $schedule->update(['user_school_role_id' => $newUsr->id]);

    $ts = Timesheet::where('schedule_id', $schedule->id)->where('date', $today->toDateString())->first();
    expect($ts->user_school_role_id)->toBe($newUsr->id);
});

test('deleting a schedule removes its future standard timesheets but keeps past ones', function () {
    $today = Carbon::today();
    ['schedule' => $schedule] = makeSyncSchedule($today->copy()->addWeek()->toDateString(), $today->dayOfWeekIso);

    // Injecte une occurrence passée directement (hors périmètre du générateur, qui ne part que d'aujourd'hui).
    $past = Timesheet::create([
        'user_school_role_id' => $schedule->user_school_role_id, 'schedule_id' => $schedule->id,
        'subject_id' => $schedule->subject_id, 'classroom_id' => $schedule->classroom_id,
        'date' => $today->copy()->subWeek()->toDateString(), 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $schedule->delete();

    expect(Timesheet::where('schedule_id', $schedule->id)->where('date', '>=', $today->toDateString())->count())->toBe(0);
    expect(Timesheet::find($past->id))->not->toBeNull();
});
