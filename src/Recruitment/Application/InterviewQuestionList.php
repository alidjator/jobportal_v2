<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\KitQuestion;

/** The questions an interview keeps. `$locked` is true once a panel member started a scorecard, which freezes the list. */
final readonly class InterviewQuestionList
{
    /** @param  list<KitQuestion>  $questions */
    public function __construct(public int $interviewId, public ?int $kitId, public ?string $kitName, public array $questions, public bool $locked) {}
}
