<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deelt validatiefouten alleen als er een sessie is (publieke GET zonder sessie blijft werken).
 */
class ShareErrorsFromSessionWhenPresent
{
    public function __construct(private readonly ViewFactory $view) {}

    public function handle(Request $request, Closure $next): Response
    {
        $errors = new ViewErrorBag(new \Illuminate\Support\MessageBag);

        if ($request->hasSession()) {
            $bag = $request->session()->get('errors');
            if ($bag instanceof ViewErrorBag) {
                $errors = $bag;
            }
        }

        $this->view->share('errors', $errors);

        return $next($request);
    }
}
