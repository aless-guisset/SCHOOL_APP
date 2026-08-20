<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\SchoolPendingNotification;

beforeEach(function () {
    $this->adminRole = Role::create([
        'name' => 'Administrateur', 'reference' => 'ADMIN',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->school = School::create([
        'name' => 'Admin School', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->profRole = Role::create([
        'name' => 'Professeur', 'reference' => 'PROF',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->admin1 = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $this->admin1->id, 'school_id' => $this->school->id,
        'role_id' => $this->adminRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // $this->requester a un rôle Professeur dans la même école : nécessaire pour que le
    // middleware CheckSchoolContext le laisse passer (0 école → redirection vers
    // /school/create, ce qui casserait tout test attendant un 404 depuis un controller
    // protégé par le groupe de routes school.context).
    $this->requester = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $this->requester->id, 'school_id' => $this->school->id,
        'role_id' => $this->profRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
});

test('SchoolPendingNotification is sent via mail and database channels', function () {
    $school = School::create([
        'name' => 'Test', 'status' => 'P', 'is_active' => false, 'created_by' => $this->requester->id,
    ]);
    $notification = new SchoolPendingNotification($school, $this->requester);

    expect($notification->via($this->admin1))->toBe(['mail', 'database']);
});

test('submitting a school creates a database notification for each active admin', function () {
    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Lycée Test',
        'email' => 'lycee@example.com',
    ]);

    expect($this->admin1->notifications()->count())->toBe(1);

    $notification = $this->admin1->notifications()->first();
    expect($notification->type)->toBe(SchoolPendingNotification::class);
    expect($notification->data['title'])->toContain('Lycée Test');
    expect($notification->data['url'])->toBe('/schools/pending');
    expect($notification->read_at)->toBeNull();
});
