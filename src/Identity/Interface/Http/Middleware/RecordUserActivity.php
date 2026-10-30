<?php

namespace Src\Identity\Interface\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Src\Identity\Application\Account\RecordActivity;
use Src\Shared\Application\ActorProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Part of the `api` middleware group: a request of a signed-in public user (`auth:user`) marks the account as active today before the route runs, so the
 * answer already shows today's bucket. A request without a valid token does nothing, and only a job seeker's account is written (the repository ignores the others).
 */
final readonly class RecordUserActivity
{
    public function __construct(private AuthFactory $auth, private RecordActivity $recordActivity) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(EnforceTermsAcceptance::USER_AUTH_MIDDLEWARE, $request->route()?->gatherMiddleware() ?? [], true)) {
            $user = $this->auth->guard('user')->user();

            if ($user instanceof ActorProvider) {
                $this->recordActivity->execute((int) $user->getAuthIdentifier());
            }
        }

        return $next($request);
    }
}
