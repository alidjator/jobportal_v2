<?php

namespace Src\Recruitment\Application;

/**
 * What the sent scorecards of an interview say: the average of their scores (null when none holds a score), how many were sent and how many members gave
 * each recommendation.
 */
final readonly class InterviewResult
{
    /** @param  array{strong_no: int, no: int, yes: int, strong_yes: int}  $recommendations */
    public function __construct(public ?float $score, public int $scorecardsSent, public array $recommendations) {}

    public static function none(): self
    {
        return new self(null, 0, ['strong_no' => 0, 'no' => 0, 'yes' => 0, 'strong_yes' => 0]);
    }

    /** The positive recommendations: yes and strong yes. */
    public function yes(): int
    {
        return $this->recommendations['yes'] + $this->recommendations['strong_yes'];
    }

    /** The negative recommendations: no and strong no. */
    public function no(): int
    {
        return $this->recommendations['no'] + $this->recommendations['strong_no'];
    }
}
