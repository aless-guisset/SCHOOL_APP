<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use Carbon\Carbon;

function makeYearSettingsUsr(School $school, string $reference, string $name): UserSchoolRole
{
    $role = Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $user = User::factory()->create();

    return UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('a power user can set the school year end date', function () {
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $powerUser = makeYearSettingsUsr($school, 'POWER', 'Power User')->user;

    $target = Carbon::today()->addMonths(6)->toDateString();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->patch('/school-year', ['year_end_date' => $target])
        ->assertSessionHas('flash.type', 'success');

    expect($school->fresh()->year_end_date->toDateString())->toBe($target);
});

test('an administrateur cannot set the school year end date', function () {
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $admin = makeYearSettingsUsr($school, 'ADMIN', 'Administrateur')->user;

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->patch('/school-year', ['year_end_date' => Carbon::today()->addMonths(6)->toDateString()])
        ->assertForbidden();
});

test('a directeur cannot set the school year end date', function () {
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $directeur = makeYearSettingsUsr($school, 'DIR', 'Directeur')->user;

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->patch('/school-year', ['year_end_date' => Carbon::today()->addMonths(6)->toDateString()])
        ->assertForbidden();
});

test('setting the year end date syncs existing complete schedules immediately', function () {
    $today = Carbon::today();
    // makeSyncSchedule() (ScheduleTimesheetSyncTest.php) crée un Schedule complet
    // sans year_end_date (null) → l'observer ne génère rien à la création.
    ['school' => $school, 'schedule' => $schedule] = makeSyncSchedule(null, $today->dayOfWeekIso);
    $powerUser = makeYearSettingsUsr($school, 'POWER2', 'Power User')->user;

    expect(\App\Models\Timesheet::where('schedule_id', $schedule->id)->count())->toBe(0);

    $target = $today->copy()->addWeeks(2)->toDateString();
    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->patch('/school-year', ['year_end_date' => $target]);

    expect(\App\Models\Timesheet::where('schedule_id', $schedule->id)->count())->toBe(3);
});

test('setting the year end date on one school does not touch another schools schedules', function () {
    $today = Carbon::today();
    ['school' => $schoolA, 'schedule' => $scheduleA] = makeSyncSchedule(null, $today->dayOfWeekIso);
    ['school' => $schoolB, 'schedule' => $scheduleB] = makeSyncSchedule(null, $today->dayOfWeekIso);
    $powerUserA = makeYearSettingsUsr($schoolA, 'POWER3', 'Power User')->user;

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->patch('/school-year', ['year_end_date' => $today->copy()->addWeek()->toDateString()]);

    expect(\App\Models\Timesheet::where('schedule_id', $scheduleA->id)->count())->toBeGreaterThan(0);
    expect(\App\Models\Timesheet::where('schedule_id', $scheduleB->id)->count())->toBe(0);
    expect($schoolB->fresh()->year_end_date)->toBeNull();
});
