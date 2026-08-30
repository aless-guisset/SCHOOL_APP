<?php

use App\Models\CantineMenu;
use App\Models\CantineOrder;
use App\Models\ParentStudentLink;
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

test('a student can order a menu option for themselves', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $date = Carbon::today()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menu->id, 'date' => $date])
        ->assertRedirect();

    expect(CantineOrder::where('section_user_id', $student->id)->whereDate('date', $date)->where('cantine_menu_id', $menu->id)->exists())->toBeTrue();
});

test('ordering again the same day replaces the previous choice instead of failing', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $date = Carbon::today()->toDateString();
    $menuA = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $menuB = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menuA->id, 'date' => $date]);
    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menuB->id, 'date' => $date])
        ->assertRedirect();

    expect(CantineOrder::where('section_user_id', $student->id)->whereDate('date', $date)->count())->toBe(1);
    expect(CantineOrder::where('section_user_id', $student->id)->whereDate('date', $date)->first()->cantine_menu_id)->toBe($menuB->id);
});

test('a student cannot order for a past date', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $pastDate = Carbon::yesterday()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $pastDate, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menu->id, 'date' => $pastDate])
        ->assertSessionHasErrors('date');
});

test('a student cannot order a menu option that belongs to a different date', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $today = Carbon::today()->toDateString();
    $tomorrow = Carbon::tomorrow()->toDateString();
    $menuTomorrow = CantineMenu::create(['school_id' => $school->id, 'date' => $tomorrow, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menuTomorrow->id, 'date' => $today])
        ->assertStatus(422);
});

test('a power user cannot place an order for themselves (not a student)', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $date = Carbon::today()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menu->id, 'date' => $date])
        ->assertForbidden();
});

test('a student can cancel their own future order', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $date = Carbon::tomorrow()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $order = CantineOrder::create(['section_user_id' => $student->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/orders/{$order->id}")
        ->assertRedirect();

    expect(CantineOrder::find($order->id))->toBeNull();
});

test('a student cannot cancel another students order', function () {
    $school = makeCantineSchool();
    $studentA = makeCantineStudent($school);
    $studentB = makeCantineStudent($school);
    $studentBUser = User::find($studentB->userschoolrole->user_id);
    $date = Carbon::tomorrow()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $orderA = CantineOrder::create(['section_user_id' => $studentA->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($studentBUser)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/orders/{$orderA->id}")
        ->assertForbidden();

    expect(CantineOrder::find($orderA->id))->not->toBeNull();
});

test('a power user can mark presence for orders of a given day', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $student = makeCantineStudent($school);
    $date = Carbon::today()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $order = CantineOrder::create(['section_user_id' => $student->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/presence', [
            'date' => $date,
            'presences' => [
                ['cantine_order_id' => $order->id, 'is_present' => false, 'note' => 'Absent, non signalé'],
            ],
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->is_present)->toBeFalse();
    expect($order->note)->toBe('Absent, non signalé');
});

test('storePresences rejects a cantine_order_id from another school', function () {
    $schoolA = makeCantineSchool();
    $schoolB = makeCantineSchool();
    $powerUserA = makeCantineUsr($schoolA, makeCantineRole('POWER', 'Power User'))->user;
    $studentB = makeCantineStudent($schoolB);
    $date = Carbon::today()->toDateString();
    $menuB = CantineMenu::create(['school_id' => $schoolB->id, 'date' => $date, 'label' => 'Plat B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $orderB = CantineOrder::create(['section_user_id' => $studentB->id, 'cantine_menu_id' => $menuB->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/cantine/presence', [
            'date' => $date,
            'presences' => [['cantine_order_id' => $orderB->id, 'is_present' => false]],
        ])
        ->assertSessionHasErrors('presences.0.cantine_order_id');
});

test('cancelling an order then re-ordering the same day succeeds instead of hitting the unique constraint', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);
    $date = Carbon::today()->toDateString();
    $menuA = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $menuB = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    // Commande initiale.
    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menuA->id, 'date' => $date])
        ->assertRedirect();
    $firstOrder = CantineOrder::where('section_user_id', $student->id)->whereDate('date', $date)->first();

    // Annulation (soft delete) le même jour.
    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/orders/{$firstOrder->id}")
        ->assertRedirect();

    // Re-commande le même jour, pour une autre option : ne doit PAS 500 sur la contrainte unique.
    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menuB->id, 'date' => $date])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $activeOrders = CantineOrder::where('section_user_id', $student->id)->whereDate('date', $date)->where('is_active', true)->get();
    expect($activeOrders)->toHaveCount(1);
    expect($activeOrders->first()->cantine_menu_id)->toBe($menuB->id);
});

test('re-adding a previously deleted menu label restores it, and a genuine live duplicate is rejected', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $date = Carbon::today()->toDateString();

    // (a) ajout, suppression, puis ré-ajout du même libellé le même jour : doit restaurer, pas dupliquer.
    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => $date, 'label' => 'Plat A'])
        ->assertRedirect();
    $menu = CantineMenu::where('school_id', $school->id)->whereDate('date', $date)->where('label', 'Plat A')->first();

    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/menus/{$menu->id}")
        ->assertRedirect();

    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => $date, 'label' => 'Plat A'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(CantineMenu::withTrashed()->where('school_id', $school->id)->whereDate('date', $date)->where('label', 'Plat A')->count())->toBe(1);
    expect(CantineMenu::where('school_id', $school->id)->whereDate('date', $date)->where('label', 'Plat A')->where('is_active', true)->exists())->toBeTrue();

    // (b) ajout d'un libellé déjà actif le même jour, sans suppression : doit être rejeté proprement.
    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => $date, 'label' => 'Plat C'])
        ->assertRedirect();

    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->post('/cantine/menus', ['date' => $date, 'label' => 'Plat C'])
        ->assertSessionHasErrors('label');

    expect(CantineMenu::withTrashed()->where('school_id', $school->id)->whereDate('date', $date)->where('label', 'Plat C')->count())->toBe(1);
});

test('a parent sees their linked child\'s cantine order but cannot place one', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $date = Carbon::today()->toDateString();

    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $existingOrder = CantineOrder::create(['section_user_id' => $student->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $parentRole = makeCantineRole('PARENT', 'Parent');
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->userschoolrole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_order', false)
            ->where('my_order.id', $existingOrder->id)
        );
});

test('a parent cannot post a cantine order even by calling the route directly', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $date = Carbon::today()->toDateString();

    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $parentRole = makeCantineRole('PARENT', 'Parent');
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->userschoolrole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders', ['cantine_menu_id' => $menu->id, 'date' => $date])
        ->assertForbidden();
});

test('a teacher with a Parent role cannot place a cantine order via as_parent=1, even though they can as a teacher', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $date = Carbon::today()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $teacherParent = User::factory()->create();
    UserSchoolRole::create(['user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makeCantineRole('PROF', 'Professeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parentUsr = UserSchoolRole::create(['user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makeCantineRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->userschoolrole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacherParent)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/orders?as_parent=1', ['cantine_menu_id' => $menu->id, 'date' => $date])
        ->assertForbidden();
});

test('a teacher with a Parent role sees their child\'s order (not the roster) via as_parent=1 on GET /cantine', function () {
    $school = makeCantineSchool();
    $student = makeCantineStudent($school);
    $date = Carbon::today()->toDateString();
    $menu = CantineMenu::create(['school_id' => $school->id, 'date' => $date, 'label' => 'Plat A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $childOrder = CantineOrder::create(['section_user_id' => $student->id, 'cantine_menu_id' => $menu->id, 'date' => $date, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $teacherParent = User::factory()->create();
    UserSchoolRole::create(['user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makeCantineRole('PROF', 'Professeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parentUsr = UserSchoolRole::create(['user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makeCantineRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->userschoolrole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacherParent)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine?date='.$date.'&as_parent=1')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('roster')
            ->where('can_order', false)
            ->where('my_order.cantine_menu_id', $childOrder->cantine_menu_id)
        );
});
