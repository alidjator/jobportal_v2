<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionType;

/** A long-text or video answer waiting for, or holding, its assessor's score; `images` are the pictures of its question, so the assessor sees what was asked. */
final readonly class ScoringItem
{
    /** @param  list<QuestionImage>  $images */
    public function __construct(
        public int $questionId,
        public string $questionText,
        public int $points,
        public ?string $answerText,
        public ?float $score,
        public QuestionType $type = QuestionType::LongText,
        public array $images = [],
    ) {}
}
