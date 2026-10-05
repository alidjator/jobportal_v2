<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Interface\Http\ApiRootController;
use Src\Shared\Interface\Http\Middleware\ResolveRequestLanguage;
use Src\Shared\Interface\Http\Problem\ProblemDetailsRenderer;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // No `web` group on purpose: this API has no sessions, cookies or CSRF, so `/` answers without setting any of them.
            Route::get('/', ApiRootController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [ResolveRequestLanguage::class, EnforceTermsAcceptance::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $problems = new ProblemDetailsRenderer;

        $exceptions->render(fn (Throwable $e, Request $request) => $problems->render($e, $request));

        // A domain exception is an expected answer to a client (404, 403, 409, 422, 429...): it is rendered, never logged. Logging each one
        // with its stack trace would let anyone fill the disk by guessing tokens or failing sign-ins, and bury the real faults. An unavailable
        // external service stays reported, because it needs an operator.
        $exceptions->dontReportWhen(fn (Throwable $e): bool => $e instanceof DomainException && ! $e instanceof ExternalServiceUnavailable);
    })->create();
