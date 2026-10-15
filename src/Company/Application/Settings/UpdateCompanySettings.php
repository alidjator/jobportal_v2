<?php

namespace Src\Company\Application\Settings;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\CompanyEditableSetting;
use Src\Shared\Application\Settings\CompanySettingsEditor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A company admin changes the settings that belong to the company, all or none. Each value is saved as the company's own value, the one a platform admin
 * sees and resets. Switching `company.require_two_factor` on ends the sessions of the members who have no second factor yet.
 */
final readonly class UpdateCompanySettings
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanySettingsEditor $editor,
    ) {}

    /**
     * @param  array<string, mixed>  $values  key => new value
     * @return list<CompanyEditableSetting> the page after the change
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `setting-not-editable` or `invalid-setting-value`
     */
    public function execute(Actor $actor, array $values, ?string $ipAddress, ?string $userAgent): array
    {
        return $this->editor->update((int) $this->guard->company($actor)->id, (int) $actor->id, $values, $ipAddress, $userAgent);
    }
}
