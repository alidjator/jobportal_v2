<?php

namespace Src\Identity\Interface\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Src\Identity\Application\Terms\EnsureTermsAccepted;
use Src\Shared\Application\ActorProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * The terms gate. It is part of the `api` middleware group and acts on every route that requires a signed-in public user
 * (`auth:user`), so routes added later are protected by default. A route that must stay reachable opts out with
 * `->withoutMiddleware(EnforceTermsAcceptance::class)`. Public routes are never gated, even when the client sends a token
 * along, and admin requests are not concerned.
 */
final readonly class EnforceTermsAcceptance
{
    public const string USER_AUTH_MIDDLEWARE = 'auth:user';

    public function __construct(
        private AuthFactory $auth,
        private EnsureTermsAccepted $ensureTermsAccepted,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->requiresSignedInUser($request)) {
            $user = $this->auth->guard('user')->user();

            // A missing or invalid token is left to the `auth:user` middleware, which answers 401.
            if ($user instanceof ActorProvider) {
                $this->ensureTermsAccepted->execute((int) $user->getAuthIdentifier());
            }
        }

        return $next($request);
    }

    private function requiresSignedInUser(Request $request): bool
    {
        return in_array(self::USER_AUTH_MIDDLEWARE, $request->route()?->gatherMiddleware() ?? [], true);
    }
}
