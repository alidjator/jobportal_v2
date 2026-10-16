<?php

namespace Src\Shared\Application\Settings;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What the Organization context needs from Platform for the group settings page: the settings a group admin may change, and saving them as the group's own
 * values (read between the company's value and the platform's). Provided by Platform.
 */
interface GroupSettingsEditor
{
    /** @return list<GroupEditableSetting> sorted by key */
    public function editable(int $groupId): array;

    /**
     * Saves the values as the group's values, all or none, and audits one `group.settings_updated` entry with the values before and after. A value equal to the one
     * that applies to the group changes and audits nothing.
     *
     * @param  array<string, mixed>  $values  key => new value
     * @return list<GroupEditableSetting> the page after the change
     *
     * @throws BusinessRuleViolation `setting-not-editable` for a key that is unknown or not editable by a group; `invalid-setting-value` with `errors` (key => problem) for every value the catalog refuses
     */
    public function update(int $groupId, int $actorUserId, array $values, ?string $ipAddress, ?string $userAgent): array;
}
