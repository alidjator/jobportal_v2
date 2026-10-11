<?php

namespace Src\Assessment\Domain;

/** The result of one Kraepelin-type column: the sums the candidate answered and how many of them were right. */
final readonly class SpeedColumnResult
{
    public function __construct(
        public int $sectionId,
        public int $columnNo,
        public int $attempted,
        public int $correct,
    ) {}
}
