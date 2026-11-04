<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;

/** What an author submits for a question. */
final readonly class QuestionDraft
{
    /**
     * @param  list<QuestionOption>  $options
     * @param  list<int>  $imageIds  the pictures of the question, in the order shown
     * @param  list<?int>  $optionImageIds  the picture of each option (same order as `$options`), null for none; empty when none has one
     */
    public function __construct(
        public QuestionType $type,
        public string $text,
        public int $points,
        public ?string $scaleKey,
        public bool $isSample,
        public array $options,
        public ?int $thinkSeconds = null,
        public ?int $answerSeconds = null,
        public ?int $maxRetakes = null,
        public array $imageIds = [],
        public array $optionImageIds = [],
    ) {}
}
