<?php

use App\Models\CantineMenu;
use App\Models\CantineOrder;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function makeCantineSchool(bool $enabled = true): School
{
    return School::create([
        'name' => 'École Cantine '.uniqid(), 'status' => 'A', 'is_active' => true,
        'cantine_enabled' => $enabled, 'created_by' => 1,
    ]);
}

function makeCantineRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeCantineUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Crée un élève (section_users) rattaché à l'école. */
function makeCantineStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $studentUsr = makeCantineUsr($school, makeCantineRole('ELEVE', 'Élève'));

    return SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('the cantine module 404s when disabled for the active school', function () {
    $school = makeCantineSchool(enabled: false);
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine')
        ->assertNotFound();
});

test('index shows the roster (not my_order) for a power user, on the given date only', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $student = makeCantineStudent($school);
    $date = Carbon::today()->toDateString();

    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    // Un menu sur une AUTRE date ne doit pas apparaître.
    CantineMenu::create(['school_id' => $school->id, 'date' => Carbon::tomorrow()->toDateString(), 'label' => 'Plat B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    CantineOrder::create(['section_user_id' => $student->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', $date)
            ->has('menus', 1)
            ->has('roster', 1)
            ->where('roster.0.menu_label', 'Plat A')
            ->missing('my_order')
        );
});

test('index shows my_order (not roster) for a student', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $date = Carbon::today()->toDateString();

    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    CantineOrder::create(['section_user_id' => $student->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_order', true)
            ->where('my_order.cantine_menu_id', $menu->id)
            ->missing('roster')
        );
});

test('index gives a directeur the menu only, no roster, no ordering', function () {
    $school = makeCantineSchool();
    $directeur = makeCantineUsr($school, makeCantineRole('DIR', 'Directeur'))->user;
    $date = Carbon::today()->toDateString();

    CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->has('menus', 1)
            ->where('can_order', false)
            ->missing('roster')
        );
});

test('index scopes menus and roster to the active school', function () {
    $schoolA = makeCantineSchool();
    $schoolB = makeCantineSchool();
    $powerUserA = makeCantineUsr($schoolA, makeCantineRole('POWER', 'Power User'))->user;
    $date = Carbon::today()->toDateString();

    CantineMenu::create(['school_id' => $schoolA->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    CantineMenu::create(['school_id' => $schoolB->id, 'date' => $date, 'label' => 'Plat B (autre école)', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get('/cantine?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->has('menus', 1)
            ->where('menus.0.label', 'Plat A')
        );
});

test('a power user can add a menu option for a date', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $date = Carbon::today()->toDateString();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => $date, 'label' => 'Plat A', 'description' => 'Pâtes bolognaise'])
        ->assertRedirect();

    expect(CantineMenu::where('school_id', $school->id)->whereDate('date', $date)->where('label', 'Plat A')->exists())->toBeTrue();
});

test('a teacher can add a menu option', function () {
    $school = makeCantineSchool();
    $teacher = makeCantineUsr($school, makeCantineRole('PROF', 'Professeur'))->user;
    $date = Carbon::today()->toDateString();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => $date, 'label' => 'Plat A'])
        ->assertRedirect();

    expect(CantineMenu::where('school_id', $school->id)->exists())->toBeTrue();
});

test('an administrateur cannot add a menu option', function () {
    $school = makeCantineSchool();
    $admin = makeCantineUsr($school, makeCantineRole('ADMIN', 'Administrateur'))->user;

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => Carbon::today()->toDateString(), 'label' => 'Plat A'])
        ->assertForbidden();
});

test('a directeur cannot add a menu option', function () {
    $school = makeCantineSchool();
    $directeur = makeCantineUsr($school, makeCantineRole('DIR', 'Directeur'))->user;

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => Carbon::today()->toDateString(), 'label' => 'Plat A'])
        ->assertForbidden();
});

test('a power user can remove a menu option', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => Carbon::today()->toDateString(), 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/menus/{$menu->id}")
        ->assertRedirect();

    expect(CantineMenu::find($menu->id))->toBeNull();
});

test('destroyMenu rejects a menu belonging to another school', function () {
    $schoolA = makeCantineSchool();
    $schoolB = makeCantineSchool();
    $powerUserA = makeCantineUsr($schoolA, makeCantineRole('POWER', 'Power User'))->user;
    $menuB = CantineMenu::create(['school_id' => $schoolB->id, 'date' => Carbon::today()->toDateString(), 'label' => 'Plat B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->delete("/cantine/menus/{$menuB->id}")
        ->assertNotFound();

    expect(CantineMenu::find($menuB->id))->not->toBeNull();
});
