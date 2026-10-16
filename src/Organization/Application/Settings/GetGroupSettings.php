<?php

namespace Src\Organization\Application\Settings;

use Src\Organization\Application\Group\GroupAccess;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\GroupEditableSetting;
use Src\Shared\Application\Settings\GroupSettingsEditor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The settings a group may set, with the value that applies to the group: any member of the group reads. */
final readonly class GetGroupSettings
{
    public function __construct(private GroupAccess $access, private GroupSettingsEditor $editor) {}

    /**
     * @return list<GroupEditableSetting>
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     */
    public function execute(Actor $actor): array
    {
        return $this->editor->editable((int) $this->access->context($actor)->group->id);
    }
}
