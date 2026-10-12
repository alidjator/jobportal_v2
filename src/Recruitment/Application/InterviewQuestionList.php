<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewQuestion;

/** The questions an interview keeps. `$locked` is true once a panel member started a scorecard, which freezes the list. */
final readonly class InterviewQuestionList
{
    /** @param  list<InterviewQuestion>  $questions */
    public function __construct(public int $interviewId, public ?int $kitId, public ?string $kitName, public array $questions, public bool $locked) {}
}
