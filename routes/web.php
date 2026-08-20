<?php

use App\Http\Controllers\ActivityLogsController;
use App\Http\Controllers\ClassroomsController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicatePlanningController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\SchoolOnboardingController;
use App\Http\Controllers\SchoolPanelController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\SectionCoursesController;
use App\Http\Controllers\SectionsController;
use App\Http\Controllers\SubjectsController;
use App\Http\Controllers\TimesheetsController;
use App\Http\Controllers\TranslationsController;
use App\Http\Controllers\UserSchoolRolesController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// ─── Page d'accueil publique ───────────────────────────────────────────────
Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// ─── Onboarding école (auth requis, sans school.context) ──────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // Sélection d'école (multi-école)
    Route::get('/school/select', [SchoolOnboardingController::class, 'select'])
        ->name('school.select');
    Route::post('/school/activate', [SchoolOnboardingController::class, 'activate'])
        ->name('school.activate');

    // Demande de création d'école
    Route::get('/school/create', [SchoolOnboardingController::class, 'create'])
        ->name('school.create');
    Route::post('/school/create', [SchoolOnboardingController::class, 'store'])
        ->name('school.store');

    // Définir l'école par défaut
    Route::post('/school/set-default', [SchoolOnboardingController::class, 'setDefault'])
        ->name('school.set-default');

    // Page d'attente pour les étudiants sans école
    Route::get('/school/waiting', fn () => Inertia::render('school/Waiting'))
        ->name('school.waiting');
});

// ─── Application principale (auth + contexte école requis) ────────────────
Route::middleware(['auth', 'verified', 'school.context'])->group(function () {

    // Dashboard (router par rôle côté Vue)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::patch('/notifications/{notification}', [NotificationsController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])
        ->name('notifications.read-all');

    // ── Panel école (accessible à tous les rôles) ─────────────────────────
    Route::get('/schools/{school}/panel', SchoolPanelController::class)->name('school.panel');

    // ── Admin Plateforme ──────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::get('/schools/pending', [SchoolsController::class, 'pending'])->name('schools.pending');
        Route::post('/schools/{school}/approve', [SchoolsController::class, 'approve'])->name('schools.approve');
        Route::post('/schools/{school}/reject', [SchoolsController::class, 'reject'])->name('schools.reject');
        Route::resource('schools', SchoolsController::class);
        Route::resource('roles', RolesController::class);
        Route::resource('users', UsersController::class);
        Route::get('/logs', [ActivityLogsController::class, 'index'])->name('logs.index');
        Route::resource('translations', TranslationsController::class)->except(['show']);
    });

    // ── Admin : assignations rôles ────────────────────────────────────────
    Route::resource('user-school-roles', UserSchoolRolesController::class)
        ->only(['index', 'create', 'store', 'destroy']);

    // ── Power User / Secrétariat ──────────────────────────────────────────
    Route::resource('courses', CoursesController::class);
    Route::resource('sections', SectionsController::class);
    Route::resource('section-courses', SectionCoursesController::class);
    Route::resource('classrooms', ClassroomsController::class);
    Route::resource('subjects', SubjectsController::class);
    Route::resource('lessons', LessonsController::class);
    Route::resource('schedules', SchedulesController::class);
    Route::post('/planning/duplicate', DuplicatePlanningController::class)->name('planning.duplicate');
    Route::get('/timesheets/check-conflict', [TimesheetsController::class, 'checkConflict'])
        ->name('timesheets.check-conflict');
    Route::resource('timesheets', TimesheetsController::class);
    Route::resource('resources', ResourcesController::class);
});

require __DIR__.'/settings.php';
