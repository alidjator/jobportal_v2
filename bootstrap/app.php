<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Identity\Interface\Http\Middleware\EnforceTermsAcceptance;
use Src\Shared\Interface\Http\Middleware\ResolveRequestLanguage;
use Src\Shared\Interface\Http\Problem\ProblemDetailsRenderer;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
    })->create();
