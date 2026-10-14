<?php

namespace Src\Assessment\Application\Attempts;

use DateTimeImmutable;

/**
 * What the candidate sees of an attempt. `questions` is what the section lets them see now (never the correct answers); `speed` is the open Kraepelin
 * column; both are empty when the attempt is closed. `idleMinutes` is how long the candidate may give no input before the browser reports `idle`.
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
        public int $idleMinutes = 0,
        public ?string $session = null,
    ) {}

    /** The same view with the token of the device that just started the attempt. */
    public function withSession(?string $session): self
    {
        return new self($this->context, $this->questionCount, $this->questions, $this->speed, $this->idleMinutes, $session);
    }

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
