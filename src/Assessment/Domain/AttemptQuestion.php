<?php

namespace Src\Assessment\Domain;

/** A question drawn for an attempt, in the order it is asked. */
final readonly class AttemptQuestion
{
    public function __construct(
        public int $sectionId,
        public int $questionId,
        public int $position,
    ) {}
}
