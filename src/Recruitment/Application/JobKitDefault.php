<?php

namespace Src\Recruitment\Application;

/** The kit a job preselects for one interview stage. */
final readonly class JobKitDefault
{
    public function __construct(public int $stageId, public string $stageName, public int $kitId, public string $kitName) {}
}
