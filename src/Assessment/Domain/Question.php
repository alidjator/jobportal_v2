<?php

namespace Src\Assessment\Domain;

final readonly class Question
{
    /**
     * @param  list<QuestionOption>  $options
     */
    public function __construct(
        public ?int $id,
        public int $sectionId,
        public int $position,
        public QuestionType $type,
        public string $text,
        public int $points,
        public ?string $scaleKey,
        public bool $isSample,
        public array $options,
    ) {}
}
