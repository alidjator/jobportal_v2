<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\QuestionType;

/** One answer of an application with its question, for the applicant's own detail page. */
final readonly class ApplicationAnswerView
{
    /** @param  list<array{id: int, label: string}>  $options  the chosen options of a choice question */
    public function __construct(
        public int $questionId,
        public string $questionText,
        public QuestionType $type,
        public ?string $text,
        public ?int $number,
        public ?bool $bool,
        public array $options,
    ) {}
}
