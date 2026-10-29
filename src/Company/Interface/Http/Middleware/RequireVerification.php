<?php

namespace Src\Company\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Application\Portal\VerificationFeature;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Symfony\Component\HttpFoundation\Response;

/** The routes of the verification of companies exist on the marketplace only: on a company portal they answer 404 `verification-unavailable`. */
final readonly class RequireVerification
{
    public function __construct(private VerificationFeature $verification) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws EntityNotFound `verification-unavailable`
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->verification->enabled()) {
            throw new EntityNotFound('Company verification is not available on this portal.', 'verification-unavailable');
        }

        return $next($request);
    }
}
