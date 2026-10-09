<?php

namespace Src\Shared\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cache headers of a public page that also answers a signed-in caller with something personal (the match with a job seeker's profile). Without a token it
 * sets the headers it is given, exactly as `cache.headers` does; with one the answer is private and never cached by a shared cache. Both answers vary on the
 * `Authorization` header, so a shared cache never hands the public copy to a signed-in caller or the personal one to anyone else.
 */
final class PublicCacheHeaders extends SetCacheHeaders
{
    public function handle($request, Closure $next, $options = []): Response
    {
        if ($request->bearerToken() !== null) {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');
        } else {
            $response = parent::handle($request, $next, $options);
        }

        $response->setVary('Authorization', false);

        return $response;
    }
}
