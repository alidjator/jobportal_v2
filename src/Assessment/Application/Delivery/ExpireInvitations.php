<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationChange;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * Closes the invitations the candidate never started once their due time has passed, and tells the candidate. It runs every hour. A started test is not
 * touched here: its attempt has its own deadline.
 */
final readonly class ExpireInvitations
{
    public function __construct(
        private InvitationRepository $invitations,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return int the number of invitations that expired */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->invitations->dueForExpiry($now) as $due) {
            $event = $this->transactions->run(function () use ($due, $now): ?InvitationChanged {
                $invitation = $this->invitations->find((int) $due->id, forUpdate: true);

                // Changed since it was listed: started, cancelled or already expired.
                if (! $invitation instanceof Invitation || $invitation->status !== InvitationStatus::Invited || $invitation->dueAt > $now) {
                    return null;
                }

                $expired = $invitation->expire($now);
                $this->invitations->update($expired);

                return InvitationChanged::of(InvitationChange::Expired, $expired, $now);
            });

            if ($event instanceof InvitationChanged) {
                $this->events->dispatch([$event]);
                $count++;
            }
        }

        return $count;
    }
}
