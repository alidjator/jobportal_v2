<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One invitation of the signed-in job seeker. Opening it the first time turns `sent` into `viewed`. Someone else's invitation does not exist. */
final readonly class GetMyInvitation
{
    public function __construct(
        private InvitationAccess $access,
        private InvitationRepository $invitations,
        private InvitationViews $views,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): InvitationView
    {
        $userId = $this->access->jobSeeker($actor);

        $invitation = $this->transactions->run(function () use ($userId, $id) {
            $found = $this->invitations->findOfUser($userId, $id, forUpdate: true) ?? throw EntityNotFound::of('Invitation', $id);
            $viewed = $found->viewed($this->clock->now());

            if ($viewed !== $found) {
                $this->invitations->save($viewed);
            }

            return $viewed;
        });

        return $this->views->forCandidate(new Page([$invitation], 1, new PageRequest))->items[0];
    }
}
