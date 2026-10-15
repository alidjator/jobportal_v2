<?php

namespace Src\Company\Application\Settings;

use Src\Company\Application\Profile\CompanyMemberGuard;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\CompanyEditableSetting;
use Src\Shared\Application\Settings\CompanySettingsEditor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The settings of the actor's own company that its company admin may change, with the platform default and the allowed range of each. Any member may read them. */
final readonly class GetCompanySettings
{
    public function __construct(
        private CompanyMemberGuard $guard,
        private CompanySettingsEditor $editor,
    ) {}

    /**
     * @return list<CompanyEditableSetting>
     *
     * @throws AccessDenied `not-a-company-member`
     */
    public function execute(Actor $actor): array
    {
        return $this->editor->editable((int) $this->guard->company($actor)->id);
    }
}
