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

test('unreadNotifications prop reflects the current user notifications', function () {
    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Lycée Partagé',
        'email' => 'partage@example.com',
    ]);

    $response = $this->actingAs($this->admin1)
        ->withSession(['active_school_id' => $this->school->id])
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('unreadNotifications.count', 1)
        ->has('unreadNotifications.items', 1)
        ->where('unreadNotifications.items.0.title', fn ($title) => str_contains($title, 'Lycée Partagé'))
        ->where('unreadNotifications.items.0.read', false)
    );
});

test('unreadNotifications is empty when the user has no notifications', function () {
    $response = $this->actingAs($this->admin1)
        ->withSession(['active_school_id' => $this->school->id])
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('unreadNotifications.count', 0)
        ->has('unreadNotifications.items', 0)
    );
});

test('a user can mark their own notification as read', function () {
    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Lycée À Lire', 'email' => 'alire@example.com',
    ]);

    $notification = $this->admin1->notifications()->first();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($this->admin1)
        ->withSession(['active_school_id' => $this->school->id])
        ->patch("/notifications/{$notification->id}");

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Lycée Protégé', 'email' => 'protege@example.com',
    ]);

    $notification = $this->admin1->notifications()->first();

    $response = $this->actingAs($this->requester)
        ->withSession(['active_school_id' => $this->school->id])
        ->patch("/notifications/{$notification->id}");

    $response->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark-all-read only affects the current user notifications', function () {
    $admin2 = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $admin2->id, 'school_id' => $this->school->id,
        'role_id' => $this->adminRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Lycée Multi', 'email' => 'multi@example.com',
    ]);

    $this->actingAs($this->admin1)
        ->withSession(['active_school_id' => $this->school->id])
        ->post('/notifications/read-all');

    expect($this->admin1->unreadNotifications()->count())->toBe(0);
    expect($admin2->unreadNotifications()->count())->toBe(1);
});
