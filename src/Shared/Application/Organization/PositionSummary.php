<?php

namespace Src\Shared\Application\Organization;

/** A position of a legal entity as it is on a day: its stable id and code, and the data of the version in force. */
final readonly class PositionSummary
{
    public function __construct(
        public int $id,
        public int $legalEntityId,
        public string $code,
        public string $title,
        public int $unitId,
        public ?int $minGradeId,
        public ?int $maxGradeId,
        public ?int $jobRoleId,
        public bool $active,
    ) {}
}
