<?php

namespace Src\Assessment\Application\Results;

/** A long-text answer waiting for, or holding, its assessor's score. */
final readonly class ScoringItem
{
    public function __construct(
        public int $questionId,
        public string $questionText,
        public int $points,
        public string $answerText,
        public ?float $score,
    ) {}
}
