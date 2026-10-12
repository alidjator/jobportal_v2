<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationDeclined;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The candidate turns a test down before starting it, typically because they do not want to be recorded. The invitation is kept as history and no longer blocks
 * the application, so the team can send another test or plan a live interview. The team that sent it is told.
 */
final readonly class DeclineInvitation
{
    public function __construct(
        private InvitationRepository $invitations,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `invitation-not-open`
     */
    public function execute(Actor $actor, int $invitationId): void
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        $event = $this->transactions->run(function () use ($actor, $invitationId): InvitationDeclined {
            $invitation = $this->invitations->find($invitationId, forUpdate: true);

            if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $actor->id) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            $now = $this->clock->now();
            $declined = $invitation->decline($now);
            $this->invitations->update($declined);

            return InvitationDeclined::of($declined, $now);
        });

        $this->events->dispatch([$event]);
    }
}
