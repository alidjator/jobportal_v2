<?php

namespace Src\Recruitment\Domain;

/** The score and note of one scorecard question; a null score means the question was skipped. */
final readonly class ScorecardAnswer
{
    public function __construct(public int $questionId, public ?int $score, public ?string $note) {}
}
