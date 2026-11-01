<?php

use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\CanonicalHostRedirect;
use App\Http\Middleware\NoIndexHeader;
use App\Http\Middleware\RecordPageView;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ShareErrorsFromSessionWhenPresent;
use App\Http\Middleware\StartSessionWhenNeeded;
use App\Http\Middleware\StripIndexPhpRedirect;
use App\Http\Middleware\UpdateLastSeen;
use App\Http\Middleware\VerifyCsrfTokenWhenSession;
use App\Support\ThrottleMessage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(StripIndexPhpRedirect::class);
        $middleware->append(CanonicalHostRedirect::class);
        $middleware->append(NoIndexHeader::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(replace: [
            StartSession::class => StartSessionWhenNeeded::class,
            ShareErrorsFromSession::class => ShareErrorsFromSessionWhenPresent::class,
            ValidateCsrfToken::class => VerifyCsrfTokenWhenSession::class,
        ]);
        $middleware->appendToGroup('web', RecordPageView::class);
        $middleware->appendToGroup('web', UpdateLastSeen::class);
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'deploy/run',
            'deploy/import',
            'deploy/import/init',
            'deploy/import/chunk',
        ]);
        $middleware->alias([
            'admin' => AdminAuth::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('account.inloggen'));
        $middleware->redirectUsersTo(fn () => route('account.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $message = ThrottleMessage::forSeconds($retry);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429, $e->getHeaders());
            }

            return response()
                ->view('errors.429', ['exception' => new TooManyRequestsHttpException($retry, $message)], 429)
                ->withHeaders($e->getHeaders());
        });
    })->create();
