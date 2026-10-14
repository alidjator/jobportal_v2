<?php

namespace Src\Assessment\Application\Attempts;

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
        $events = [];

        $view = $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use (&$events): AttemptView {
            $submitted = $this->submitter->submit($context, auto: false);
            $events = $submitted->events();

            return $this->views->build($context->withAttempt($submitted->attempt));
        });

        if ($events !== []) {
            $this->events->dispatch($events);
        }

        return $view;
    }
}
