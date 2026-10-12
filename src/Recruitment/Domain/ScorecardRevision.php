<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** The content of a sent scorecard before one edit. `$othersVisible` tells whether another member's scorecard was already sent at that time (anchoring). */
final readonly class ScorecardRevision
{
    /** @param  array{recommendation: ?string, note: ?string, score: ?float, answers: list<array{question_id: int, score: ?int, note: ?string}>}  $previous */
    public function __construct(public int $scorecardId, public array $previous, public bool $othersVisible, public DateTimeImmutable $createdAt) {}
}
