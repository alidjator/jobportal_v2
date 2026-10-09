<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The dashboard of an admin, kept for 5 minutes. The route already asks for `dashboard.view`; a card that shows another module's data (a work queue) is added only when
 * the admin also holds that module's permission. The cache is shared by the admins who hold the same permissions: the numbers are the same for them.
 */
final readonly class GetAdminDashboard
{
    private const int CACHE_SECONDS = 300;

    public function __construct(private DashboardComposer $composer, private DashboardCards $cards) {}

    /**
     * @param  list<string>  $held  the permissions the admin holds (any, the cards ask for a few)
     *
     * @throws AccessDenied `admin-only`
     */
    public function execute(Actor $actor, array $held): DashboardView
    {
        if (! $actor->isAdmin() || $actor->id === null) {
            throw new AccessDenied('Only an admin has this dashboard.', 'admin-only');
        }

        $permissions = array_values(array_intersect($this->cards->permissionsFor(DashboardAudience::Admin), $held));
        sort($permissions);

        return $this->composer->compose(
            new DashboardScope(DashboardAudience::Admin, $actor->id, null, null, $permissions),
            'dashboard:admin:'.md5(implode(',', $permissions)),
            self::CACHE_SECONDS,
        );
    }
}
