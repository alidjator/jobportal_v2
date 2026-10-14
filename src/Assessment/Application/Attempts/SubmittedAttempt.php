<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Shared\Domain\DomainEvent;

/** An attempt after it was submitted, with the events to dispatch once the transaction has committed. */
final readonly class SubmittedAttempt
{
    public function __construct(
        public Attempt $attempt,
        public AttemptSubmitted $event,
        public ?ReportSigned $issued = null,
    ) {}

    /** @return list<DomainEvent> the events to dispatch: the submission, and the report that was issued with it */
    public function events(): array
    {
        return $this->issued === null ? [$this->event] : [$this->event, $this->issued];
    }
}
