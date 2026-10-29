<?php

use App\Http\Middleware\NoIndexHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(NoIndexHeader::class);
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('account.inloggen'));
        $middleware->redirectUsersTo(fn () => route('account.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
