<?php

namespace Src\Company\Application\Presets;

use Src\Company\Domain\PermissionPreset;

/** A saved preset with the number of members who hold exactly its permissions. */
final readonly class PermissionPresetUsage
{
    public function __construct(public PermissionPreset $preset, public int $memberCount) {}
}
