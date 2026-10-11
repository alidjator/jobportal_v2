<?php

namespace Src\Assessment\Domain;

/** A choice, an accepted answer (number, short text) or a scored statement (Likert, forced choice). */
final readonly class QuestionOption
{
    public function __construct(
        public string $label,
        public bool $isCorrect = false,
        public ?int $scoreValue = null,
        public ?string $scaleKey = null,
    ) {}
}
