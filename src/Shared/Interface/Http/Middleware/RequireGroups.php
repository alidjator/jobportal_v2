<?php

namespace Src\Shared\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Application\Portal\GroupFeature;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Symfony\Component\HttpFoundation\Response;

/** The routes of the groups of the ERP exist where groups do (`GroupFeature`): on the portal of one company they answer 404 `groups-unavailable`. */
final readonly class RequireGroups
{
    public function __construct(private GroupFeature $groups) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws EntityNotFound `groups-unavailable`
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->groups->enabled()) {
            throw new EntityNotFound('Groups are not available on this portal.', 'groups-unavailable');
        }

        return $next($request);
    }
}
