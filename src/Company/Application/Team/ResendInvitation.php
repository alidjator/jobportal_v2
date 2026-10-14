<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Application\Invitations\IssueInvitation;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyInvitationResent;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Domain\InvitationToken;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * A company admin resends a pending or expired invitation: a new token and 7 more days, the same email, role and permissions; the old link stops working.
 * The one-minute cooldown of inviting applies.
 */
final readonly class ResendInvitation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private InvitationRepository $invitations,
        private InvitationEmailDomains $domains,
        private CompanyMemberAccounts $accounts,
        private InvitationLinkBuilder $links,
        private InvitationSender $sender,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the company has no pending or expired invitation with that id
     * @throws BusinessRuleViolation `email-domain-not-allowed`
     * @throws StateConflict `email-already-registered`
     * @throws TooManyAttempts `invitation-cooldown`
     * @throws ExternalServiceUnavailable `invitation-link-unavailable`
     */
    public function execute(Actor $actor, int $invitationId, string $language, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;
        $this->links->assertAvailable();

        $token = InvitationToken::generate();

        $invitation = $this->transactions->run(function () use ($companyId, $invitationId, $token): Invitation {
            $now = $this->clock->now();
            $this->companies->lock($companyId);

            $invitation = $this->invitations->findOfCompany($companyId, $invitationId, forUpdate: true);

            if ($invitation === null || (! $invitation->isPending($now) && ! $invitation->isExpired($now))) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            $this->domains->assertAllowed($companyId, $invitation->email);

            if ($this->accounts->emailIsRegistered($invitation->email)) {
                throw new StateConflict('An account with this email already exists.', 'email-already-registered');
            }

            $wait = $invitation->sentAt->getTimestamp() + IssueInvitation::COOLDOWN_SECONDS - $now->getTimestamp();

            if ($wait > 0) {
                throw new TooManyAttempts('Wait a moment before sending this invitation again.', $wait, 'invitation-cooldown');
            }

            return $this->invitations->save($invitation->resent($token->hash(), $now));
        });

        $this->sender->send($invitation->email, $language, $company->name(), $invitation->role, $this->links->build($token->plain), $invitation->expiresAt);
        $this->events->dispatch([new CompanyInvitationResent($companyId, (int) $invitation->id, (int) $actor->id, $ipAddress, $userAgent, $invitation->sentAt)]);
    }
}
