<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** A submitted invitation whose long-text answers wait for scoring, with the time the candidate submitted the test. */
final readonly class AwaitingScoring
{
    public function __construct(public Invitation $invitation, public DateTimeImmutable $submittedAt) {}
}
