<?php

namespace Src\JobSeeker\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Application\Portal\CompanyBlockingFeature;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Symfony\Component\HttpFoundation\Response;

/** The routes of the blocked companies exist on the marketplace only: on a company portal they answer 404 `company-blocking-unavailable`. */
final readonly class RequireCompanyBlocking
{
    public function __construct(private CompanyBlockingFeature $blocking) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws EntityNotFound `company-blocking-unavailable`
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->blocking->enabled()) {
            throw new EntityNotFound('Blocking companies is not available on this portal.', 'company-blocking-unavailable');
        }

        return $next($request);
    }
}
