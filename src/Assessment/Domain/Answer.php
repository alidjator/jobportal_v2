<?php

namespace Src\Assessment\Domain;

/** What a candidate answered to one question: the positions of the chosen options, a number or a text. Only the field of the question's type is set. */
final readonly class Answer
{
    /** @param  list<int>  $optionPositions  1-based positions in the question's options */
    public function __construct(
        public int $questionId,
        public array $optionPositions = [],
        public ?float $numberValue = null,
        public ?string $textValue = null,
        public ?float $score = null,
    ) {}

    public function withScore(?float $score): self
    {
        return new self($this->questionId, $this->optionPositions, $this->numberValue, $this->textValue, $score);
    }
}
