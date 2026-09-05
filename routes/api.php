<?php

use App\Http\Controllers\ClassroomsController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubjectsController;
use App\Http\Controllers\TimesheetsController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());

    Route::name('api.')->group(function () {
        // Gestion plateforme : réservé aux admins, comme le groupe `admin` de routes/web.php.
        Route::middleware('admin')->group(function () {
            Route::apiResource('schools', SchoolsController::class);
            Route::apiResource('users', UsersController::class);
            Route::apiResource('roles', RolesController::class);
        });

        // Lecture ouverte à tout rôle de l'école active, écriture réservée aux
        // rôles de gestion — même modèle que routes/web.php.
        Route::apiResource('classrooms', ClassroomsController::class)->only(['index', 'show']);
        Route::apiResource('subjects', SubjectsController::class)->only(['index', 'show']);
        Route::apiResource('lessons', LessonsController::class)->only(['index', 'show']);
        Route::apiResource('schedules', SchedulesController::class)->only(['index', 'show']);
        Route::apiResource('timesheets', TimesheetsController::class)->only(['index', 'show']);
        Route::apiResource('resources', ResourcesController::class)->only(['index', 'show']);

        Route::middleware('can-manage')->group(function () {
            Route::apiResource('classrooms', ClassroomsController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('subjects', SubjectsController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('lessons', LessonsController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('schedules', SchedulesController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('timesheets', TimesheetsController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('resources', ResourcesController::class)->only(['store', 'update', 'destroy']);
        });
    });
});
