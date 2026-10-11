<?php

namespace Src\Assessment\Application\Attempts;

use DateTimeImmutable;

/**
 * What the candidate sees of an attempt. `questions` is what the section lets them see now (never the correct answers); `speed` is the open Kraepelin
 * column; both are empty when the attempt is closed.
 */
final readonly class AttemptView
{
    /**
     * @param  list<DeliveredQuestion>  $questions
     * @param  array{column: int, columns: int, digits: list<int>, column_ends_at: DateTimeImmutable, submitted_columns: list<int>}|null  $speed
     */
    public function __construct(
        public AttemptContext $context,
        public int $questionCount,
        public array $questions,
        public ?array $speed,
    ) {}

    /** `in_progress`, `submitted` or `closed` (cancelled by the team). */
    public function status(): string
    {
        return match (true) {
            $this->context->attempt->isSubmitted() => 'submitted',
            $this->context->isOpen() => 'in_progress',
            default => 'closed',
        };
    }
}
