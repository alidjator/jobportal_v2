<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Identity\Interface\Http\Middleware\EnsureCompanyRole;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Interface\Http\ApiResponse;
use Src\Shared\Interface\Http\Middleware\ResolveRequestContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [ResolveRequestContext::class]);
        $middleware->alias(['api_company' => EnsureCompanyRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (AuthenticationException $e) => ApiResponse::unauthenticated());
        $exceptions->render(fn (EntityNotFound $e) => ApiResponse::notFound($e->getMessage()));
        $exceptions->render(fn (ModelNotFoundException $e) => ApiResponse::notFound('Not Found'));
        $exceptions->render(fn (NotFoundHttpException $e, Request $r) => $r->is('api/*') ? ApiResponse::notFound() : null);
        $exceptions->render(fn (BusinessRuleViolation $e) => ApiResponse::error($e->getMessage()));
        $exceptions->render(fn (AccessDenied $e) => ApiResponse::forbidden($e->getMessage() ?: null));
    })->create();
