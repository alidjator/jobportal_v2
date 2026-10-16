<?php

namespace Src\Company\Domain;

use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The rules a member's set of permissions must meet, on invitation and on change: at least one, reading (`applications.view`) with every action that needs it, and reviewing (`applications.review`) with managing. */
final class MemberPermissionRules
{
    /**
     * @param  list<string>  $names  permission names already checked against the catalog
     * @return list<string> the names without duplicates, in catalog order
     *
     * @throws BusinessRuleViolation `permissions-required` for an empty set, `permission-requires-view` when an action comes without reading, `permission-requires-review` when managing comes without reviewing
     */
    public static function validated(array $names): array
    {
        $held = array_values(array_filter(CompanyPermission::cases(), static fn (CompanyPermission $permission): bool => in_array($permission->value, $names, true)));

        if ($held === []) {
            throw new BusinessRuleViolation('A member needs at least one permission.', 'permissions-required');
        }

        if (! in_array(CompanyPermission::ApplicationsView, $held, true)) {
            foreach ($held as $permission) {
                if ($permission->requiresView()) {
                    throw new BusinessRuleViolation(
                        "The permission {$permission->value} needs applications.view.", 'permission-requires-view', extensions: ['permission' => $permission->value],
                    );
                }
            }
        }

        if (! in_array(CompanyPermission::ApplicationsReview, $held, true)) {
            foreach ($held as $permission) {
                if ($permission->requiresReview()) {
                    throw new BusinessRuleViolation(
                        "The permission {$permission->value} needs applications.review.", 'permission-requires-review', extensions: ['permission' => $permission->value],
                    );
                }
            }
        }

        return array_map(static fn (CompanyPermission $permission): string => $permission->value, $held);
    }
}
