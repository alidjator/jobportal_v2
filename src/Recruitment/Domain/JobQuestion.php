<?php

namespace Src\Recruitment\Domain;

/** A library question as one job uses it: the flags and the ideal answer belong to this attachment. */
final readonly class JobQuestion
{
    public function __construct(
        public ScreeningQuestion $question,
        public int $position,
        public bool $isRequired,
        public bool $isMustHave,
        public ?IdealAnswer $ideal,
    ) {}
}
