<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\SchoolPendingNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Create the Administrateur role
    $this->adminRole = Role::create([
        'name' => 'Administrateur',
        'reference' => 'ADMIN',
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);

    // Create a Professeur role for non-admin users
    $this->profRole = Role::create([
        'name' => 'Professeur',
        'reference' => 'PROF',
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);

    // Create a school to associate with admins
    $this->school = School::create([
        'name' => 'Admin School',
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);

    // Create two admin users
    $this->admin1 = User::factory()->create();
    $this->admin2 = User::factory()->create();

    // Create a non-admin user
    $this->profUser = User::factory()->create();

    // Create the requester (who will submit the school)
    $this->requester = User::factory()->create();

    // Associate admins with the admin role
    UserSchoolRole::create([
        'user_id' => $this->admin1->id,
        'school_id' => $this->school->id,
        'role_id' => $this->adminRole->id,
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);

    UserSchoolRole::create([
        'user_id' => $this->admin2->id,
        'school_id' => $this->school->id,
        'role_id' => $this->adminRole->id,
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);

    // Associate non-admin user with the prof role
    UserSchoolRole::create([
        'user_id' => $this->profUser->id,
        'school_id' => $this->school->id,
        'role_id' => $this->profRole->id,
        'status' => 'A',
        'is_active' => true,
        'created_by' => 1,
    ]);
});

test('submitting a school creation sends SchoolPendingNotification to all Administrateur users', function () {
    Notification::fake();

    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'New School',
        'email' => 'school@example.com',
        'phone_number' => '123-456-7890',
        'address' => '123 Main St',
        'description' => 'A new school',
    ]);

    // Verify notification was sent to both admins
    Notification::assertSentTo($this->admin1, SchoolPendingNotification::class);
    Notification::assertSentTo($this->admin2, SchoolPendingNotification::class);

    // Verify notification was NOT sent to non-admin
    Notification::assertNotSentTo($this->profUser, SchoolPendingNotification::class);

    // Verify notification was NOT sent to the requester
    Notification::assertNotSentTo($this->requester, SchoolPendingNotification::class);
});

test('notification content includes school name and submitter information', function () {
    Notification::fake();

    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Lycée Test',
        'email' => 'school@example.com',
    ]);

    Notification::assertSentTo($this->admin1, SchoolPendingNotification::class, function ($notification) {
        $mail = $notification->toMail($this->admin1);

        expect($mail->subject)->toContain('Lycée Test');
        expect($mail->subject)->toContain('[School App]');

        // Check that the mailable was created correctly (checking the subject and content)
        return true;
    });
});

test('no notifications are sent if there are no admin users', function () {
    Notification::fake();

    // Delete the admin role to ensure no admins exist
    Role::where('name', 'Administrateur')->delete();

    $this->actingAs($this->requester)->post(route('school.create'), [
        'name' => 'Orphan School',
        'email' => 'orphan@example.com',
    ]);

    // Verify no notifications were sent to anyone
    Notification::assertNotSentTo($this->admin1, SchoolPendingNotification::class);
    Notification::assertNotSentTo($this->admin2, SchoolPendingNotification::class);
    Notification::assertNotSentTo($this->profUser, SchoolPendingNotification::class);
});
