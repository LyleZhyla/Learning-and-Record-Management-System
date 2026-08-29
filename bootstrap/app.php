<?php

use App\Http\Middleware\AuditUserActivity;
use App\Http\Middleware\EnforceInactivityTimeout;
use App\Http\Middleware\EnsureUserIsCoordinator;
use App\Http\Middleware\EnsureUserIsFacilitator;
use App\Http\Middleware\EnsureUserIsNstpAdmin;
use App\Http\Middleware\EnsureUserIsStudent;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            AuditUserActivity::class,
            EnforceInactivityTimeout::class,
        ]);

        $middleware->alias([
            'super_admin' => EnsureUserIsSuperAdmin::class,
            'nstp_admin' => EnsureUserIsNstpAdmin::class,
            'coordinator' => EnsureUserIsCoordinator::class,
            'facilitator' => EnsureUserIsFacilitator::class,
            'student' => EnsureUserIsStudent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
