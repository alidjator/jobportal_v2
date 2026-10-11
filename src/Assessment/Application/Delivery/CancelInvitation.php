<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationChange;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The team cancels an active test invitation (one that is invited or started). The application stays in its stage. The candidate is told. */
final readonly class CancelInvitation
{
    public function __construct(
        private TeamApplications $applications,
        private InvitationRepository $invitations,
        private InvitationViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `invitation-not-active`
     */
    public function execute(Actor $actor, int $invitationId): InvitationView
    {
        $invitation = $this->invitations->find($invitationId) ?? throw EntityNotFound::of('Invitation', $invitationId);

        [$cancelled, $event] = $this->transactions->run(function () use ($actor, $invitation, $invitationId): array {
            // The application first, as every other change of an application does; a member who may not see it meets a 404.
            $this->applications->find($actor, $invitation->applicationId, forUpdate: true);
            $current = $this->invitations->find($invitationId, forUpdate: true) ?? throw EntityNotFound::of('Invitation', $invitationId);

            $cancelled = $current->cancel($this->clock->now());
            $this->invitations->update($cancelled);

            return [$cancelled, InvitationChanged::of(InvitationChange::Cancelled, $cancelled, $this->clock->now())];
        });

        $this->events->dispatch([$event]);

        return $this->views->one($cancelled);
    }
}
