<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AttemptRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The candidate is done with the open section and opens the next one now. The time left in the section is not carried over. The last section ends with a submit. */
final readonly class FinishSection
{
    public function __construct(
        private AttemptGate $gate,
        private AttemptViews $views,
        private AttemptRepository $attempts,
    ) {}

    /**
     * @throws StateConflict `attempt-not-started`, `attempt-closed` or `last-section`
     */
    public function execute(Actor $actor, int $invitationId, ?string $ip): AttemptView
    {
        return $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context): AttemptView {
            if ($context->attempt->sectionPosition >= count($context->sections)) {
                throw new StateConflict('This is the last section: submit the test to finish it.', 'last-section');
            }

            $next = $context->attempt->nextSection($context->now, $context->durations);
            $this->attempts->update($next);

            return $this->views->build($context->withAttempt($next));
        });
    }
}
