<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\ReviewAnswer;

/** The answer of one reviewer about one application: `$answered` false while it is pending; the decision and the reason are null for a pending answer and when the viewer may not see it yet. */
final readonly class ReviewAnswerView
{
    public function __construct(
        public int $reviewerId,
        public ?string $reviewerName,
        public bool $mine,
        public bool $answered,
        public ?string $decision,
        public ?string $reason,
        public ?DateTimeImmutable $answeredAt,
    ) {}

    public static function of(int $reviewerId, ?string $name, bool $mine, ?ReviewAnswer $answer, bool $hidden): self
    {
        return new self($reviewerId, $name, $mine, $answer !== null, $hidden || $answer === null ? null : $answer->decision->value, $hidden ? null : $answer?->reason, $hidden ? null : $answer?->answeredAt);
    }
}
