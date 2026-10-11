<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/** Submits the attempts whose last section has closed and whose candidate never came back to hand them in. It runs every minute; the candidate who comes back finds the same. */
final readonly class AutoSubmitAttempts
{
    public function __construct(
        private AttemptRepository $attempts,
        private InvitationRepository $invitations,
        private AttemptContexts $contexts,
        private AttemptSubmitter $submitter,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return int the number of attempts submitted */
    public function execute(): int
    {
        $count = 0;

        foreach ($this->attempts->idsOutOfTime($this->clock->now()) as $id) {
            $event = $this->transactions->run(function () use ($id): ?AttemptSubmitted {
                $found = $this->attempts->find($id);
                $invitation = $found === null ? null : $this->invitations->find($found->invitationId, forUpdate: true);
                $attempt = $found === null ? null : $this->attempts->find($id, forUpdate: true);

                // Changed since it was listed: submitted by the candidate or cancelled by the team.
                if (! $attempt || ! $invitation instanceof Invitation || $attempt->isSubmitted() || $invitation->status !== InvitationStatus::Started) {
                    return null;
                }

                $now = $this->clock->now();
                $context = $this->contexts->load($invitation, $attempt, $now);

                if (! $attempt->rolledTo($now, $context->durations)->isOutOfTime($context->durations)) {
                    return null;
                }

                return $this->submitter->submit($context, auto: true)->event;
            });

            if ($event instanceof AttemptSubmitted) {
                $this->events->dispatch([$event]);
                $count++;
            }
        }

        return $count;
    }
}
