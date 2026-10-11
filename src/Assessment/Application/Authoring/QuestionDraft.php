<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;

/** What an author submits for a question. */
final readonly class QuestionDraft
{
    /**
     * @param  list<QuestionOption>  $options
     */
    public function __construct(
        public QuestionType $type,
        public string $text,
        public int $points,
        public ?string $scaleKey,
        public bool $isSample,
        public array $options,
    ) {}
}
