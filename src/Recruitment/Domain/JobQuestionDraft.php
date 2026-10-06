<?php

namespace Src\Recruitment\Domain;

/** One question of the new list of a job, checked and ready to be written. */
final readonly class JobQuestionDraft
{
    public function __construct(
        public int $questionId,
        public int $position,
        public bool $isRequired,
        public bool $isMustHave,
        public ?IdealAnswer $ideal,
    ) {}
}
