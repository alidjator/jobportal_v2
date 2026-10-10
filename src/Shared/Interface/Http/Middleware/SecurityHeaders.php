<?php

namespace Src\Shared\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security headers of every API answer. A header a response already carries is kept (file downloads set their own `Content-Security-Policy`), and the
 * policy that blocks everything is added to JSON answers only. HSTS is not set here: it belongs to the TLS terminator (nginx).
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->setIfAbsent($response, 'X-Content-Type-Options', 'nosniff');
        $this->setIfAbsent($response, 'X-Frame-Options', 'DENY');
        $this->setIfAbsent($response, 'Referrer-Policy', 'no-referrer');

        if ($response instanceof JsonResponse || str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $this->setIfAbsent($response, 'Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }

        return $response;
    }

    private function setIfAbsent(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}
