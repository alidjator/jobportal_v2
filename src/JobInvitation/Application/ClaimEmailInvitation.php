<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A signed-in job seeker with a verified email claims an invitation. The account email may differ from the invited one: the token proves the person received it. The invitation
 * is linked to the account; the front end then opens the normal apply form. Claiming again with the same account gives the same answer.
 */
final readonly class ClaimEmailInvitation
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private ApplicantContacts $contacts,
        private CompanyDirectory $companies,
        private JobDirectory $jobs,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `email-not-verified` or `invitation-closed`
     */
    public function execute(Actor $actor, string $token): ClaimedEmailInvitation
    {
        $this->access->assertEnabled();

        $userId = $this->access->jobSeeker($actor);

        if (! ($this->contacts->contact($userId)->emailVerified ?? false)) {
            throw new StateConflict('Verify your email address before continuing.', 'email-not-verified');
        }

        $hash = InvitationToken::of($token)->hash();

        $invitation = $this->transactions->run(function () use ($hash, $token, $userId): EmailInvitation {
            $locked = $this->invitations->findByTokenHash($hash, forUpdate: true) ?? throw EntityNotFound::of('Invitation', substr($token, 0, 4));

            if ($locked->claimedByUserId === $userId) {
                return $locked;
            }

            $claimed = $locked->claimedBy($userId, $this->clock->now());
            $this->invitations->save($claimed);

            return $claimed;
        });

        return new ClaimedEmailInvitation(
            $invitation->jobId,
            $this->jobs->titles([$invitation->jobId])[$invitation->jobId] ?? null,
            $this->companies->summaries([$invitation->companyId])[$invitation->companyId]->name ?? null,
            $invitation->channel,
            $invitation->hasCv(),
            $invitation->cvName,
        );
    }
}
