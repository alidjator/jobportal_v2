<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\Events\AttemptSubmitted;

/** An attempt after it was submitted, with the event to dispatch once the transaction has committed. */
final readonly class SubmittedAttempt
{
    public function __construct(
        public Attempt $attempt,
        public AttemptSubmitted $event,
    ) {}
}
