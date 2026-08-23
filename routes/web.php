<?php

use App\Http\Controllers\ActivityLogsController;
use App\Http\Controllers\AttendancesController;
use App\Http\Controllers\CantineController;
use App\Http\Controllers\ClassroomsController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\SchoolOnboardingController;
use App\Http\Controllers\SchoolPanelController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\SchoolYearSettingsController;
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

    // Notifications (aucun contexte école requis pour marquer comme lu)
    Route::patch('/notifications/{notification}', [NotificationsController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])
        ->name('notifications.read-all');
});

// ─── Application principale (auth + contexte école requis) ────────────────
Route::middleware(['auth', 'verified', 'school.context'])->group(function () {

    // Dashboard (router par rôle côté Vue)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Panel école (accessible à tous les rôles) ─────────────────────────
    Route::get('/schools/{school}/panel', SchoolPanelController::class)->name('school.panel');

    // ── Admin Plateforme ──────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::get('/schools/pending', [SchoolsController::class, 'pending'])->name('schools.pending');
        Route::post('/schools/{school}/approve', [SchoolsController::class, 'approve'])->name('schools.approve');
        Route::post('/schools/{school}/reject', [SchoolsController::class, 'reject'])->name('schools.reject');
        Route::resource('schools', SchoolsController::class);
        Route::resource('roles', RolesController::class);
        // Route littérale déclarée avant le resource : `show` (`{user}`) est un
        // joker qui capturerait sinon "export" comme un id de show.
        Route::get('/users/export', [UsersController::class, 'export'])->name('users.export');
        Route::resource('users', UsersController::class);
        Route::get('/logs', [ActivityLogsController::class, 'index'])->name('logs.index');
        Route::resource('translations', TranslationsController::class)->except(['show']);

        // ── Admin : assignations rôles ─────────────────────────────────────
        Route::resource('user-school-roles', UserSchoolRolesController::class)
            ->only(['index', 'create', 'store', 'destroy']);
    });

    // ── Power User / Secrétariat ────────────────────────────────────────────
    // Lecture ouverte à tout rôle de l'école active (Professeur/Élève inclus,
    // cf. CLAUDE.md « Professeur / Étudiant : lecture ») ; écriture réservée
    // aux rôles de gestion (mêmes rôles que MANAGE_ROLES/canManage).
    //
    // Le bloc écriture (avec ses routes littérales `create`/`edit` et
    // `timesheets/check-conflict`) DOIT être enregistré avant le bloc lecture :
    // `show` (`{course}`) est un joker qui capture tout segment non reconnu
    // plus tôt dans la table de routage, y compris "create" ou "check-conflict".
    $writeOnly = ['create', 'store', 'edit', 'update', 'destroy'];

    Route::middleware('can-manage')->group(function () use ($writeOnly) {
        Route::resource('courses', CoursesController::class)->only($writeOnly);
        Route::resource('sections', SectionsController::class)->only($writeOnly);
        Route::resource('section-courses', SectionCoursesController::class)->only($writeOnly);
        Route::resource('classrooms', ClassroomsController::class)->only($writeOnly);
        Route::resource('subjects', SubjectsController::class)->only($writeOnly);
        Route::resource('lessons', LessonsController::class)->only($writeOnly);
        Route::resource('schedules', SchedulesController::class)->only($writeOnly);
        Route::patch('/school-year', [SchoolYearSettingsController::class, 'update'])->name('school-year.update');
        Route::get('/timesheets/check-conflict', [TimesheetsController::class, 'checkConflict'])
            ->name('timesheets.check-conflict');
        Route::resource('timesheets', TimesheetsController::class)->only($writeOnly);
        Route::post('/timesheets/{timesheet}/attendance', [AttendancesController::class, 'store'])
            ->name('attendances.store');
        Route::resource('resources', ResourcesController::class)->only($writeOnly);

        // ── Cantine (module optionnel, activable par école) ────────────────
        Route::post('/cantine/menus', [CantineController::class, 'storeMenu'])->name('cantine.menus.store');
        Route::delete('/cantine/menus/{cantineMenu}', [CantineController::class, 'destroyMenu'])->name('cantine.menus.destroy');
        Route::post('/cantine/presence', [CantineController::class, 'storePresences'])->name('cantine.presence.store');

        // ── Notes / bulletins ────────────────────────────────────────────────
        Route::get('/grades/create', [GradesController::class, 'create'])->name('grades.create');
        Route::post('/grades', [GradesController::class, 'store'])->name('grades.store');
        Route::delete('/grades/{grade}', [GradesController::class, 'destroy'])->name('grades.destroy');
    });

    Route::get('/cantine', [CantineController::class, 'index'])->name('cantine.index');
    Route::post('/cantine/orders', [CantineController::class, 'storeOrder'])->name('cantine.orders.store');
    Route::delete('/cantine/orders/{cantineOrder}', [CantineController::class, 'destroyOrder'])->name('cantine.orders.destroy');

    Route::get('/grades', [GradesController::class, 'index'])->name('grades.index');
    Route::get('/grades/bulletin/{sectionUser}', [GradesController::class, 'bulletin'])->name('grades.bulletin');

    Route::resource('courses', CoursesController::class)->only(['index', 'show']);
    Route::resource('sections', SectionsController::class)->only(['index', 'show']);
    Route::resource('section-courses', SectionCoursesController::class)->only(['index', 'show']);
    Route::resource('classrooms', ClassroomsController::class)->only(['index', 'show']);
    Route::resource('subjects', SubjectsController::class)->only(['index', 'show']);
    Route::resource('lessons', LessonsController::class)->only(['index', 'show']);
    Route::resource('schedules', SchedulesController::class)->only(['index', 'show']);
    Route::resource('timesheets', TimesheetsController::class)->only(['index', 'show']);
    Route::resource('resources', ResourcesController::class)->only(['index', 'show']);
});

require __DIR__.'/settings.php';
