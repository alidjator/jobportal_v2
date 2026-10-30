<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The person opens the link (public). The first opening is recorded; a token that is unknown, or any token while the feature is off, is a plain "not found". */
final readonly class ShowEmailInvitation
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private CompanyDirectory $companies,
        private JobDirectory $jobs,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound */
    public function execute(string $token): PublicEmailInvitation
    {
        $this->access->assertEnabled();

        $hash = InvitationToken::of($token)->hash();
        $invitation = $this->transactions->run(function () use ($hash, $token): EmailInvitation {
            $locked = $this->invitations->findByTokenHash($hash, forUpdate: true) ?? throw EntityNotFound::of('Invitation', substr($token, 0, 4));
            $opened = $locked->claimable($this->clock->now()) ? $locked->opened($this->clock->now()) : $locked;

            if ($opened !== $locked) {
                $this->invitations->save($opened);
            }

            return $opened;
        });

        return new PublicEmailInvitation(
            $this->companies->summaries([$invitation->companyId])[$invitation->companyId]->name ?? null,
            $invitation->jobId,
            $this->jobs->titles([$invitation->jobId])[$invitation->jobId] ?? null,
            $invitation->status->isOpen() ? $invitation->note : null,
            $invitation->channel,
            $invitation->claimable($this->clock->now()),
            $this->jobs->liveJobIds([$invitation->jobId]) !== [],
        );
    }
}
