<?php

namespace Src\Organization\Application\Structure;

/** The data of a new position. */
final readonly class PositionInput
{
    public function __construct(
        public string $title,
        public int $unitId,
        public ?int $minGradeId,
        public ?int $maxGradeId,
        public ?int $reportsToPositionId,
        public ?int $jobRoleId,
    ) {}
}
