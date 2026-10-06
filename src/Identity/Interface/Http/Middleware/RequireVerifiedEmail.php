<?php

namespace Src\Identity\Interface\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Src\Identity\Application\Account\EnsureEmailVerified;
use Src\Shared\Application\ActorProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in gate for actions that affect other parties: a route adds `verified.email` after `auth:user`. A missing or invalid token
 * is left to `auth:user`, which answers 401.
 */
final readonly class RequireVerifiedEmail
{
    public const string ALIAS = 'verified.email';

    public function __construct(
        private AuthFactory $auth,
        private EnsureEmailVerified $ensureEmailVerified,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->guard('user')->user();

        if ($user instanceof ActorProvider) {
            $this->ensureEmailVerified->execute((int) $user->getAuthIdentifier());
        }

        return $next($request);
    }
}
