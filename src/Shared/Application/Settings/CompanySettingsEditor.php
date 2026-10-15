<?php

namespace Src\Shared\Application\Settings;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What the Company context needs from Platform for the company settings page: the settings a company admin may change, and saving them as the company's
 * own values (the same override a platform admin sees and resets). Provided by Platform.
 */
interface CompanySettingsEditor
{
    /** @return list<CompanyEditableSetting> sorted by key */
    public function editable(int $companyId): array;

    /**
     * Saves the values as the company's overrides, all or none, and audits one `company.settings_updated` entry with the values before and after. A value
     * equal to the one that applies changes and audits nothing.
     *
     * @param  array<string, mixed>  $values  key => new value
     * @return list<CompanyEditableSetting> the page after the change
     *
     * @throws BusinessRuleViolation `setting-not-editable` for a key that is unknown or not editable by a company; `invalid-setting-value` with `errors` (key => problem) for every value the catalog refuses
     */
    public function update(int $companyId, int $actorUserId, array $values, ?string $ipAddress, ?string $userAgent): array;
}
