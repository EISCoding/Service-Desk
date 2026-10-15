<?php

use App\Http\Middleware\EnsureContactCanUsePortal;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RedirectToInstaller;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fresh installation: everything leads to the browser installer (/install).
        $middleware->web(prepend: [RedirectToInstaller::class]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'portal' => EnsureContactCanUsePortal::class,
        ]);

        // The portal has its own login and start page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal', 'portal/*') ? route('portal.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('portal', 'portal/*') ? route('portal.home') : route('intern.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
