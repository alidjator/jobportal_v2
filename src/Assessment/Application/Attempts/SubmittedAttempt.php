<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Assessment\Domain\Events\ResultFinalized;
use Src\Shared\Domain\DomainEvent;

/** An attempt after it was submitted, with the events to dispatch once the transaction has committed. */
final readonly class SubmittedAttempt
{
    public function __construct(
        public Attempt $attempt,
        public AttemptSubmitted $event,
        public ?ReportSigned $issued = null,
        public ?ResultFinalized $finalized = null,
    ) {}

    /** @return list<DomainEvent> the events to dispatch: the submission, the report that was issued with it, and the final result of a skill test that needs no assessor */
    public function events(): array
    {
        return array_values(array_filter([$this->event, $this->issued, $this->finalized], static fn (?DomainEvent $event): bool => $event !== null));
    }
}
