<?php

namespace Src\Recruitment\Application;

/** What the sent scorecards of an interview gave one of its questions: the average of the scores and how many there were (skipped questions do not count). */
final readonly class QuestionScore
{
    public function __construct(public string $text, public ?float $average, public int $count) {}
}
