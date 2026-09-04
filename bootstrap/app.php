<?php

use App\Http\Middleware\CheckSchoolContext;
use App\Http\Middleware\EnsureAdministrateur;
use App\Http\Middleware\EnsureCanManage;
use App\Http\Middleware\EnsureCanSubmitSchool;
use App\Http\Middleware\EnsureDirecteur;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'school.context'    => CheckSchoolContext::class,
            'admin'             => EnsureAdministrateur::class,
            'can-manage'        => EnsureCanManage::class,
            'director-only'     => EnsureDirecteur::class,
            'can-submit-school' => EnsureCanSubmitSchool::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
