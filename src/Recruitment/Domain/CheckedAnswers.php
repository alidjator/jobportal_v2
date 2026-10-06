<?php

namespace Src\Recruitment\Domain;

/** The answers of an application after checking them against the job's questions, and whether they meet every must-have (null when the job has none). */
final readonly class CheckedAnswers
{
    /** @param  list<ApplicationAnswer>  $answers */
    public function __construct(public array $answers, public ?bool $meetsMustHave) {}
}
