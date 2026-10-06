<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/** One stage of the company's pipeline with the number of applications of a job in it. */
final readonly class StageCount
{
    public function __construct(public int $stageId, public StageType $type, public string $name, public int $count) {}
}
