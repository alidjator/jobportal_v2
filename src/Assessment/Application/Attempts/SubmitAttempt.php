<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Shared\Application\Actor;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The candidate hands the test in. The answers are scored at once, the invitation is submitted and the team is told. A submitted attempt is locked. */
final readonly class SubmitAttempt
{
    public function __construct(
        private AttemptGate $gate,
        private AttemptSubmitter $submitter,
        private AttemptViews $views,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws StateConflict `attempt-not-started` or `attempt-closed`
     */
    public function execute(Actor $actor, int $invitationId, ?string $ip): AttemptView
    {
        $event = null;

        $view = $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use (&$event): AttemptView {
            $submitted = $this->submitter->submit($context, auto: false);
            $event = $submitted->event;

            return $this->views->build($context->withAttempt($submitted->attempt));
        });

        if ($event instanceof AttemptSubmitted) {
            $this->events->dispatch([$event]);
        }

        return $view;
    }
}
