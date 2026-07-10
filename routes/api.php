<?php

use App\Http\Controllers\ClassroomsController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\SubjectsController;
use App\Http\Controllers\TimesheetsController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());

    Route::apiResource('schools', SchoolsController::class);
    Route::apiResource('users', UsersController::class);
    Route::apiResource('roles', RolesController::class);
    Route::apiResource('classrooms', ClassroomsController::class);
    Route::apiResource('subjects', SubjectsController::class);
    Route::apiResource('lessons', LessonsController::class);
    Route::apiResource('schedules', SchedulesController::class);
    Route::apiResource('timesheets', TimesheetsController::class);
    Route::apiResource('resources', ResourcesController::class);
});
