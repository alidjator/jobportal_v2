<?php

namespace Src\Recruitment\Domain;

/** One choice of a choice question. */
final readonly class QuestionOption
{
    public function __construct(public int $id, public string $label, public int $position) {}
}
