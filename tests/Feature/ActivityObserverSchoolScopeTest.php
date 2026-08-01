<?php

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;

test('activity log stores school_id for a model with a direct school_id column', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($course)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log resolves school_id through course for a Subject (no direct school_id column)', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($subject)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log resolves school_id through a soft-deleted course for a Subject', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $course->delete();

    $subject->update(['name' => 'Algèbre avancée']);

    expect(ActivityLog::forModel($subject)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log leaves school_id null for User and Role', function () {
    $user = User::factory()->create();
    $role = Role::create([
        'name' => 'Test Role', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($user)->latest()->first()->school_id)->toBeNull();
    expect(ActivityLog::forModel($role)->latest()->first()->school_id)->toBeNull();
});
