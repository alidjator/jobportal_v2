<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The candidate declines an open invitation whose job is live, with an optional reason the inviting member can read. The member is told. */
final readonly class DeclineInvitation
{
    public function __construct(
        private InvitationAccess $access,
        private InvitationRepository $invitations,
        private JobDirectory $jobs,
        private InvitationViews $views,
        private InvitationNotifier $notifier,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `invitation-closed` (already answered, or the job is not live)
     * @throws BusinessRuleViolation `reason-too-long`
     */
    public function execute(Actor $actor, int $id, ?string $reason): InvitationView
    {
        $userId = $this->access->jobSeeker($actor);

        $invitation = $this->transactions->run(function () use ($userId, $id, $reason) {
            $found = $this->invitations->findOfUser($userId, $id, forUpdate: true) ?? throw EntityNotFound::of('Invitation', $id);

            if ($this->jobs->liveJobIds([$found->jobId]) === []) {
                throw new StateConflict('This invitation expired because the job is not live.', 'invitation-closed');
            }

            $declined = $found->decline($reason, $this->clock->now());
            $this->invitations->save($declined);

            return $declined;
        });

        $this->notifier->declined($invitation);

        return $this->views->forCandidate(new Page([$invitation], 1, new PageRequest))->items[0];
    }
}
