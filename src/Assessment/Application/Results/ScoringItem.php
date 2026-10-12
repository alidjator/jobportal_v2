<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\QuestionType;

/** A long-text or video answer waiting for, or holding, its assessor's score. */
final readonly class ScoringItem
{
    public function __construct(
        public int $questionId,
        public string $questionText,
        public int $points,
        public ?string $answerText,
        public ?float $score,
        public QuestionType $type = QuestionType::LongText,
    ) {}
}
