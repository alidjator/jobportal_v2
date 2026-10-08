<?php

namespace Src\LegacyImport\Application;

use Src\Recruitment\Domain\StageType;

/** Where the applications of one legacy pipeline group go: a stage type, and whether an extra stage named like the group is created for it. */
final readonly class StageGroupTarget
{
    public function __construct(public StageType $type, public bool $createStage) {}
}
