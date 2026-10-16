<?php

namespace Src\Organization\Application\Settings;

use Src\Organization\Application\Group\GroupAccess;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Settings\GroupEditableSetting;
use Src\Shared\Application\Settings\GroupSettingsEditor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A group admin with `group.settings.manage` changes the settings that belong to the group, all or none. Each value is saved as the group's own value: it applies to
 * every tenant of the group that has no value of its own for the setting.
 */
final readonly class UpdateGroupSettings
{
    public function __construct(private GroupAccess $access, private GroupSettingsEditor $editor) {}

    /**
     * @param  array<string, mixed>  $values  key => new value
     * @return list<GroupEditableSetting> the page after the change
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws BusinessRuleViolation `setting-not-editable` or `invalid-setting-value`
     */
    public function execute(Actor $actor, array $values, ?string $ipAddress, ?string $userAgent): array
    {
        $context = $this->access->context($actor, GroupPermission::SettingsManage);

        return $this->editor->update((int) $context->group->id, (int) $actor->id, $values, $ipAddress, $userAgent);
    }
}
