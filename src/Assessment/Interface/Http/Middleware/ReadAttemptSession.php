<?php

namespace Src\Assessment\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Assessment\Application\Attempts\AttemptSessionToken;
use Symfony\Component\HttpFoundation\Response;

/** Hands the header `X-Attempt-Session` (the token of the device taking the test) to `AttemptGate`, which decides whether that device holds the attempt. */
final readonly class ReadAttemptSession
{
    public function __construct(private AttemptSessionToken $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->session->set($request->header('X-Attempt-Session'));

        return $next($request);
    }
}
