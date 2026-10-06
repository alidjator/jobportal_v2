<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\QuestionOption;
use Src\Recruitment\Domain\QuestionType;

/** A question as an applicant sees it. It has no must-have flag and no ideal answer on purpose: they are never shown to the public. */
final readonly class PublicScreeningQuestion
{
    /** @param  list<QuestionOption>  $options */
    public function __construct(
        public int $id,
        public QuestionType $type,
        public string $text,
        public array $options,
        public bool $required,
        public int $position,
    ) {}
}
