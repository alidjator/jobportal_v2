<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\RequestContext;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The team sends the link again: once a day, at most three times. The link gets a new token, so the old one stops working. */
final readonly class ResendEmailInvitation
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private EmailInvitationNotifier $notifier,
        private EmailSuppressions $suppressions,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `feature-disabled`
     * @throws EntityNotFound
     * @throws StateConflict `invitation-closed`
     * @throws BusinessRuleViolation `resend-limit` or `email-suppressed`
     */
    public function execute(Actor $actor, int $id, ?string $language = null): EmailInvitation
    {
        $invitation = $this->invitations->find($id) ?? throw EntityNotFound::of('Invitation', $id);
        $member = $this->access->member($actor, CompanyPermission::ApplicationsManage, $invitation->jobId);

        if ($invitation->companyId !== $member->companyId) {
            throw EntityNotFound::of('Invitation', $id);
        }

        $token = InvitationToken::generate();

        $saved = $this->transactions->run(function () use ($id, $token): EmailInvitation {
            $locked = $this->invitations->find($id, forUpdate: true) ?? throw EntityNotFound::of('Invitation', $id);

            if ($locked->email !== null && $this->suppressions->reason($locked->email) !== null) {
                throw new BusinessRuleViolation('This address does not receive email.', 'email-suppressed');
            }

            $resent = $locked->resent($token->hash(), $this->clock->now());
            $this->invitations->save($resent);

            return $resent;
        });

        $this->notifier->send($saved, $token, $language ?? RequestContext::DEFAULT_LANGUAGE);

        return $saved;
    }
}
