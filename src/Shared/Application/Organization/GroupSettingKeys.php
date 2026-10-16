<?php

namespace Src\Shared\Application\Organization;

/** The keys of the settings that have the group scope, shared because the context that registers a setting is not always the one that reads it. */
final class GroupSettingKeys
{
    /** Whether every member of every internal tenant of the group must sign in with a second factor (`docs/design/erp-sso.md` section 3); on by default. */
    public const string REQUIRE_TWO_FACTOR = 'group.require_two_factor';
}
