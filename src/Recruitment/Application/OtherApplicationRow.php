<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;

/** Another application of the same person to the same company, as stored. */
final readonly class OtherApplicationRow
{
    public function __construct(public int $id, public int $jobId, public StageType $stageType, public string $stageName, public DateTimeImmutable $appliedAt) {}
}
